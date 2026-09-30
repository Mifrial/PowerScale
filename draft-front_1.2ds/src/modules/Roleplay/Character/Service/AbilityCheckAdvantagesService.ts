import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CheckAdvantageQuery } from '@/modules/Roleplay/Character/Dto/CheckAdvantageQuery';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ScalarFormula } from '@/modules/Roleplay/Rule/Dto/Ability/ScalarFormula';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

export class AbilityCheckAdvantagesService {
  constructor(
    private readonly aggregate = aggregateSourceDeltasService,
    private readonly formula = new FormulaEvaluationService(),
  ) {}
  checkAdvantageModifiersFromAbilities(
    version: Pick<CharacterVersion, 'abilities'> | null | undefined,
    rules: Rule[],
    query?: CheckAdvantageQuery,
  ): AdvantageModifier[] {
    if (!version || query?.kind !== 'check') return [];
    const entries: AdvantageModifier[] = [];
    for (const ability of version.abilities) {
      if (ability.level < 1) continue;
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      if (!rule || rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;
      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          if (grant.type !== 'check_advantage') continue;
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== ability.level) continue;
          if (!grant.check_codes.includes(query.code) || grant.amount === 0) continue;
          entries.push({
            source_code: grant.source_code ?? rule.code,
            source_label: rule.name,
            delta: grant.amount,
          });
        }
      }
    }

    return this.aggregate.aggregateSourceDeltas(entries);
  }

  checkCharacteristicModifiersFromAbilities(
    version: Pick<CharacterVersion, 'abilities'> | null | undefined,
    rules: Rule[],
    checkCode: string,
    characteristicCode: string,
  ): AdvantageModifier[] {
    if (!version) return [];
    const entries: AdvantageModifier[] = [];
    for (const ability of version.abilities) {
      if (ability.level < 1) continue;
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      if (!rule || rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;
      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          if (
            grant.type !== 'characteristic_modify' ||
            grant.characteristic_code !== characteristicCode ||
            !grant.check_codes?.includes(checkCode)
          ) {
            continue;
          }
          if (grant.permanent === false && entry.level !== ability.level) continue;
          const delta = this.formulaValue(grant.amount, ability.parameters, version.abilities, rules);
          if (delta === 0) continue;
          entries.push({
            source_code: grant.source_code ?? rule.code,
            source_label: rule.name,
            delta,
          });
        }
      }
    }

    return this.aggregate.aggregateSourceDeltas(entries);
  }

  private formulaValue(
    formula: ScalarFormula,
    parameters: CharacterVersion['abilities'][number]['parameters'],
    abilities: CharacterVersion['abilities'],
    rules: Rule[],
  ): number {
    const abilityLevels = new Map<string, number>();
    for (const ability of abilities) {
      const code = rules.find((rule) => rule.code === ability.ruleCode)?.code;
      if (code) abilityLevels.set(code, ability.level);
    }

    if (this.formula.readsCharacteristics(formula)) {
      throw new Error('Формула размера характеристики недоступна в контексте проверки');
    }

    return this.formula.evaluate(formula, {
      characteristicValues: new Map(),
      abilityLevels,
      parameterValues: (code) => this.parameterScalar(parameters?.[code]),
    });
  }

  private parameterScalar(raw: number | DimensionalNumberValue | undefined): number | undefined {
    if (typeof raw === 'number') return raw;
    if (raw?.size === 0) return raw.base;
    if (raw) throw new Error('Размерный параметр не входит в скалярную формулу');

    return undefined;
  }
}
