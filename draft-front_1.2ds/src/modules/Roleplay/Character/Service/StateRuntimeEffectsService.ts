import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { AccumulatedStateEffects } from '@/modules/Roleplay/Character/Dto/AccumulatedStateEffects';
import type { CheckAdvantageQuery } from '@/modules/Roleplay/Character/Dto/CheckAdvantageQuery';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { StateEffect } from '@/modules/Roleplay/Rule/Dto/State/StateEffect';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/Constant/CHARACTERISTIC_BASE_RANGE';
import { ADVANTAGE_SOURCE_STATE } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import { derivedCharacteristicService } from '@/modules/Roleplay/Rule/Service/Instance/derivedCharacteristicService';

export class StateRuntimeEffectsService {
  /** Характеристика — сама `root` или производная (formula min/max) от неё. */
  characteristicDependsOn(code: string, root: string, rules: Rule[], seen = new Set<string>()): boolean {
    if (code === root) return true;
    if (seen.has(code)) return false;
    seen.add(code);
    const spec = this.characteristicSpecOf(rules.find((item) => item.code === code && item.type === 'characteristic'));
    const formula = spec?.formula;
    if (!formula) return false;
    const parsed = derivedCharacteristicService.parseDerivedFormula(formula);
    if (!parsed) return false;

    return parsed.codes.some((item) => this.characteristicDependsOn(item, root, rules, seen));
  }

  /** Величина записи: флаг → 1, число → value, размерное → toNumber. */
  stateMagnitude(state: CharacterStateValue, spec: StateSpec | null): number {
    if (!spec) return state.value ?? 1;
    if (spec.value_type === 'flag') return 1;
    if (spec.value_type === 'dimensional') {
      const value = state.dimensionalValue;
      if (!value) return 0;

      return new DimensionalNumber(value).toNumber();
    }

    return state.value ?? 0;
  }

  accumulateStateEffects(states: CharacterStateValue[], rules: Rule[]): AccumulatedStateEffects {
    const characteristicDeltas = new Map<string, number>();
    const resourceLimitModify = new Map<string, number>();
    const resourceLimitSet = new Map<string, number>();

    for (const state of states) {
      const rule = this.ruleById(rules, state.stateRuleId);
      const spec = this.specOf(rule);
      if (!spec) continue;
      const magnitude = this.stateMagnitude(state, spec);
      for (const effect of spec.effects ?? []) {
        this.applyEffect(effect, magnitude, characteristicDeltas, resourceLimitModify, resourceLimitSet);
      }
    }

    return { characteristicDeltas, resourceLimitModify, resourceLimitSet };
  }

  applyCharacteristicStateDeltas(
    values: Map<string, DimensionalNumberValue>,
    deltas: Map<string, number>,
  ): Map<string, DimensionalNumberValue> {
    const next = new Map(values);
    for (const [code, amount] of deltas) {
      if (!amount) continue;
      const current = next.get(code) ?? { base: 0, size: 0 };
      next.set(code, new DimensionalNumber(current).modify(amount, CHARACTERISTIC_BASE_RANGE).value);
    }

    return next;
  }

  sheetCharacteristicValues(version: CharacterVersion, rules: Rule[]): Map<string, DimensionalNumberValue> {
    const values = new Map<string, DimensionalNumberValue>();
    for (const characteristic of version.characteristics) {
      const rule = this.ruleById(rules, characteristic.ruleId);
      if (!rule) continue;
      const delta = characteristic.modifiers.reduce((sum, modifier) => sum + modifier.delta, 0);
      values.set(rule.code, new DimensionalNumber(characteristic.base).modify(delta, CHARACTERISTIC_BASE_RANGE).value);
    }

    return values;
  }

  effectiveCharacteristicValues(version: CharacterVersion, rules: Rule[]): Map<string, DimensionalNumberValue> {
    const effects = this.accumulateStateEffects(version.states, rules);

    return this.applyCharacteristicStateDeltas(
      this.sheetCharacteristicValues(version, rules),
      effects.characteristicDeltas,
    );
  }

  checkAdvantageFromStates(
    version: CharacterVersion | null | undefined,
    rules: Rule[],
    query?: CheckAdvantageQuery,
  ): number {
    if (!version) return 0;
    let total = 0;
    for (const state of version.states) {
      const rule = this.ruleById(rules, state.stateRuleId);
      const spec = this.specOf(rule);
      if (!spec) continue;
      const magnitude = this.stateMagnitude(state, spec);
      for (const effect of spec.effects ?? []) {
        if (effect.type !== 'check_advantage') continue;
        if (!this.checkAdvantageMatchesQuery(effect, query, rules)) continue;
        total += this.scaledAmount(effect, magnitude);
      }
    }

    return total;
  }

  checkAdvantageModifiers(
    version: CharacterVersion | null | undefined,
    rules: Rule[],
    query?: CheckAdvantageQuery,
  ): AdvantageModifier[] {
    const delta = this.checkAdvantageFromStates(version, rules, query);
    if (!delta) return [];

    return [{ source_code: ADVANTAGE_SOURCE_STATE, source_label: 'состояния', delta }];
  }

  private characteristicSpecOf(rule: Rule | undefined): CharacteristicSpec | null {
    if (!rule || rule.type !== 'characteristic') return null;
    const spec = rule.spec as CharacteristicSpec | undefined;

    return spec?.type === 'characteristic' ? spec : null;
  }

  private checkAdvantageIsUnscoped(effect: Extract<StateEffect, { type: 'check_advantage' }>): boolean {
    return !effect.includes_hit && (effect.characteristic_codes?.length ?? 0) === 0;
  }

  private checkAdvantageMatchesQuery(
    effect: Extract<StateEffect, { type: 'check_advantage' }>,
    query: CheckAdvantageQuery | undefined,
    rules: Rule[],
  ): boolean {
    if (this.checkAdvantageIsUnscoped(effect)) return true;
    if (!query) return false;
    if (query.kind === 'hit') return Boolean(effect.includes_hit);

    return (effect.characteristic_codes ?? []).some((root) => this.characteristicDependsOn(query.code, root, rules));
  }

  private specOf(rule: Rule | undefined): StateSpec | null {
    if (!rule || rule.type !== 'state') return null;

    return (rule.spec as StateSpec | undefined) ?? null;
  }

  private ruleById(rules: Rule[], id: string): Rule | undefined {
    return rules.find((rule) => rule.id === id);
  }

  private scaledAmount(effect: { amount: number; per_unit?: boolean }, magnitude: number): number {
    if (magnitude === 0) return 0;

    return effect.per_unit ? effect.amount * magnitude : effect.amount;
  }

  private applyEffect(
    effect: StateEffect,
    magnitude: number,
    characteristicDeltas: Map<string, number>,
    resourceLimitModify: Map<string, number>,
    resourceLimitSet: Map<string, number>,
  ): void {
    if (effect.type === 'characteristic_modify') {
      const delta = this.scaledAmount(effect, magnitude);
      characteristicDeltas.set(
        effect.characteristic_code,
        (characteristicDeltas.get(effect.characteristic_code) ?? 0) + delta,
      );

      return;
    }
    if (effect.type === 'resource_limit_modify') {
      const delta = this.scaledAmount(effect, magnitude);
      resourceLimitModify.set(effect.resource_code, (resourceLimitModify.get(effect.resource_code) ?? 0) + delta);

      return;
    }
    if (effect.type === 'resource_limit_set') {
      const previous = resourceLimitSet.get(effect.resource_code);
      const next = previous === undefined ? effect.value : Math.min(previous, effect.value);
      resourceLimitSet.set(effect.resource_code, next);
    }
  }
}
