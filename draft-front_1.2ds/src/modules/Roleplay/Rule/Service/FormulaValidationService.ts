import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const SCALAR_TYPES = new Set([
  'fixed',
  'parameter',
  'parameter_floor_div',
  'ability_level',
  'to_scalar',
  'characteristic_size',
  'characteristic_size_positive',
  'characteristic_size_gap',
]);

const DIMENSIONAL_TYPES = new Set(['fixed', 'dimensional', 'characteristic', 'parameter', 'actionCharacteristic']);

/**
 * Проверка Formula при сохранении правила: kind поля, делитель и kind параметра.
 */
export class FormulaValidationService {
  validate(rules: Rule[]): { ruleCode: string; ruleName: string; message: string }[] {
    const errors: { ruleCode: string; ruleName: string; message: string }[] = [];
    for (const rule of rules) {
      if (rule.type === 'ability') this.validateAbility(rule, errors);
      if (rule.type === 'item') this.validateItem(rule, errors);
      if (rule.type === 'resource') this.validateResource(rule, errors);
      if (rule.type === 'state') this.validateStateDecay(rule, errors);
      if (rule.type === 'poison') this.validatePoisonDecay(rule, errors);
    }

    return errors;
  }

  private validateAbility(rule: Rule, errors: { ruleCode: string; ruleName: string; message: string }[]): void {
    const spec = rule.spec as AbilitySpec | undefined;
    if (!spec || spec.type === 'group') return;
    const parameters = spec.parameters;
    for (const parameter of parameters ?? []) {
      if (parameter.kind !== 'scalar' && parameter.kind !== 'dimensional') {
        errors.push(this.error(rule, `у параметра «${parameter.code}» не объявлен kind`));
      }
    }
    for (const level of spec.grants ?? []) {
      for (const grant of level.grants ?? []) this.validateGrant(rule, grant, parameters, errors);
    }
  }

  private validateGrant(
    rule: Rule,
    grant: Grant,
    parameters: AbilityParameter[] | undefined,
    errors: { ruleCode: string; ruleName: string; message: string }[],
  ): void {
    if (
      grant.type === 'characteristic_modify' ||
      grant.type === 'resource_limit_change' ||
      grant.type === 'sense_modify' ||
      grant.type === 'state_modify'
    ) {
      const problem = this.scalarProblem(grant.amount, parameters);
      if (problem) errors.push(this.error(rule, problem));
    }
    if (grant.type === 'magic_study' && typeof grant.max_cost !== 'number') {
      const problem = this.scalarProblem(grant.max_cost, parameters);
      if (problem) errors.push(this.error(rule, problem));
    }
    if (grant.type === 'resistance' && grant.value && typeof grant.value === 'object' && 'type' in grant.value) {
      const type = this.nodeType(grant.value);
      const problem =
        type === 'parameter' && 'per_unit' in grant.value
          ? this.scalarProblem(grant.value, parameters)
          : 'сопротивление допускает только скалярный параметр';
      if (problem) errors.push(this.error(rule, problem));
    }
  }

  private validateItem(rule: Rule, errors: { ruleCode: string; ruleName: string; message: string }[]): void {
    const item = rule.spec as ItemSpec | undefined;
    if (!item) return;
    const profiles = [...(item.weapon?.weapon_profiles ?? []), ...(item.shield?.weapon_profiles ?? [])];
    for (const profile of profiles) {
      for (const formula of [profile.distance, profile.range, profile.damage?.formula, profile.penetration]) {
        if (!formula) continue;
        const problem = this.dimensionalProblem(formula);
        if (problem) errors.push(this.error(rule, problem));
      }
      for (const entry of profile.action_characteristics ?? []) {
        const problem = this.dimensionalProblem(entry.value);
        if (problem) errors.push(this.error(rule, problem));
      }
    }
    for (const limit of [...(item.armor?.characteristic_limits ?? []), ...(item.shield?.characteristic_limits ?? [])]) {
      const problem = this.dimensionalProblem(limit.limit);
      if (problem) errors.push(this.error(rule, problem));
    }
  }

  private validateResource(rule: Rule, errors: { ruleCode: string; ruleName: string; message: string }[]): void {
    const spec = rule.spec as ResourceSpec | undefined;
    for (const adjustment of spec?.limit?.adjustments ?? []) {
      const problem = this.scalarProblem(adjustment.value, undefined);
      if (problem) errors.push(this.error(rule, problem));
    }
  }

  private validateStateDecay(rule: Rule, errors: { ruleCode: string; ruleName: string; message: string }[]): void {
    const state = rule.spec as StateSpec | undefined;
    for (const effect of state?.effects ?? []) {
      if (effect.type !== 'damage_over_time') continue;
      this.rejectIgnoredDecay(rule, effect.decay?.kind, errors);
    }
  }

  private validatePoisonDecay(rule: Rule, errors: { ruleCode: string; ruleName: string; message: string }[]): void {
    const poison = rule.spec as PoisonSpec | undefined;
    this.rejectIgnoredDecay(rule, poison?.default_decay?.kind, errors);
  }

  private rejectIgnoredDecay(
    rule: Rule,
    kind: string | undefined,
    errors: { ruleCode: string; ruleName: string; message: string }[],
  ): void {
    if (kind === 'characteristic' || kind === 'check') {
      errors.push(this.error(rule, 'затухание characteristic/check не вычисляется и недопустимо'));
    }
  }

  private scalarProblem(node: unknown, parameters: AbilityParameter[] | undefined): string | null {
    const type = this.nodeType(node);
    if (!type || !SCALAR_TYPES.has(type)) return 'скалярное поле содержит нескалярную формулу';
    if (type === 'parameter_floor_div') {
      const divisor = this.record(node).divisor;
      if (typeof divisor !== 'number' || !Number.isFinite(divisor) || divisor === 0) {
        return 'делитель parameter_floor_div должен быть ненулевым числом';
      }
    }
    if (type === 'to_scalar') {
      const inner = this.record(node).value;
      if (this.nodeType(inner) === 'to_scalar') return 'повторный to_scalar внутри to_scalar';

      return this.dimensionalProblem(inner);
    }
    if (type === 'parameter' || type === 'parameter_floor_div') {
      return this.parameterProblem(node, 'scalar', parameters);
    }
    if (type === 'actionCharacteristic') {
      return this.multiplierProblem(node);
    }

    return null;
  }

  private dimensionalProblem(node: unknown): string | null {
    const type = this.nodeType(node);
    if (!type || !DIMENSIONAL_TYPES.has(type)) return 'размерное поле содержит неразмерную формулу';
    if (type === 'parameter') return 'размерный параметр в формуле недопустим';

    return type === 'actionCharacteristic' ? this.multiplierProblem(node) : null;
  }

  private parameterProblem(
    node: unknown,
    expected: 'scalar' | 'dimensional',
    parameters: AbilityParameter[] | undefined,
  ): string | null {
    const code = this.record(node).parameter_code;
    if (typeof code !== 'string' || code === '') return 'формула параметра без кода';
    const declared = parameters?.find((parameter) => parameter.code === code);
    if (!declared) return `формула ссылается на необъявленный параметр «${code}»`;
    if (declared.kind !== expected) {
      return `параметр «${code}» объявлен как ${declared.kind}, а поле ждёт ${expected}`;
    }

    return null;
  }

  private multiplierProblem(node: unknown): string | null {
    const multiplier = this.record(node).multiplier;
    if (multiplier === undefined) return null;
    if (typeof multiplier !== 'number' || !Number.isFinite(multiplier)) {
      return 'multiplier силы действия должен быть конечным числом';
    }

    return null;
  }

  private nodeType(node: unknown): string | null {
    if (!node || typeof node !== 'object' || !('type' in node)) return null;
    const type = (node as { type?: unknown }).type;

    return typeof type === 'string' ? type : null;
  }

  private record(node: unknown): Record<string, unknown> {
    return node as Record<string, unknown>;
  }

  private error(rule: Rule, message: string): { ruleCode: string; ruleName: string; message: string } {
    return { ruleCode: rule.code, ruleName: rule.name, message };
  }
}
