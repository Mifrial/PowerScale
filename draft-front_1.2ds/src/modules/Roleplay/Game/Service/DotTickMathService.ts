import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { StateDecay } from '@/modules/Roleplay/Rule/Dto/State/StateDecay';
import type { StatePeriodicity } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';
import { BURNING_STATE_CODE, POISONING_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';

import type { DotAdvance } from '@/modules/Roleplay/Game/Dto/DotAdvance';
export class DotTickMathService {
  private asStateSpec(rule: Rule | undefined): StateSpec | null {
    if (!rule || rule.type !== 'state') return null;
    const spec = rule.spec as StateSpec | undefined;
    if (!spec || !spec.value_type) return null;

    return spec;
  }

  private asPoisonSpec(rule: Rule | undefined): PoisonSpec | null {
    if (!rule || rule.type !== 'poison') return null;
    const spec = rule.spec as PoisonSpec | undefined;
    if (!spec || !spec.damage_type_code) return null;

    return spec;
  }

  turnPeriod(periodicity: StatePeriodicity | undefined): number | null {
    if (!periodicity || periodicity.step !== 'turn') return null;
    const value = Math.floor(periodicity.value);

    return value >= 1 ? value : null;
  }

  nextDotTurnsLeft(current: number | undefined, period: number): { fire: boolean; left: number } {
    const effective = current ?? period;
    const after = effective - 1;
    if (after <= 0) return { fire: true, left: period };

    return { fire: false, left: after };
  }

  decayStrength(strength: DimensionalNumberValue, decay: StateDecay | undefined): DimensionalNumberValue | null {
    if (!decay) return strength;
    if (decay.kind === 'fixed') {
      const next = strength.base - decay.value;
      if (next <= 0) return null;

      return { ...strength, base: next };
    }
    if (decay.kind === 'dimensional') {
      const result = new DimensionalNumber(strength).subtract(
        new DimensionalNumber({ base: decay.base, size: decay.size }),
      );
      if (result.toNumber() <= 0 || result.value.base <= 0) return null;

      return result.value;
    }

    return strength;
  }

  private burningDot(state: CharacterStateValue, rule: Rule, spec: StateSpec): DotAdvance {
    const effect = spec.effects?.find((item) => item.type === 'damage_over_time');
    if (!effect) return { kind: 'skip' };
    const period = this.turnPeriod(effect.periodicity ?? { kind: 'literal', value: 1, step: 'turn' });
    if (period == null) return { kind: 'skip' };
    const strength = state.dimensionalValue;
    if (!strength || new DimensionalNumber(strength).toNumber() <= 0) return { kind: 'skip' };
    const { fire, left } = this.nextDotTurnsLeft(state.dotTurnsLeft, period);
    const next = { ...state, dotTurnsLeft: left };
    if (!fire) return { kind: 'wait', next };
    const afterDecay = this.decayStrength(strength, effect.decay);
    const remaining = afterDecay ? { ...next, dimensionalValue: afterDecay } : null;

    return {
      kind: 'tick',
      next: remaining,
      strength,
      damageTypeCode: 'fire',
      label: rule.name,
    };
  }

  private poisoningDot(state: CharacterStateValue, poisoningRule: Rule, rules: Rule[]): DotAdvance {
    const poison = state.poison;
    if (!poison) return { kind: 'skip' };
    const template = poison.poisonRuleId ? rules.find((rule) => rule.id === poison.poisonRuleId) : undefined;
    const spec = this.asPoisonSpec(template);
    const periodicity = poison.periodicity ?? spec?.default_periodicity;
    const period = this.turnPeriod(periodicity);
    if (period == null) return { kind: 'skip' };
    const strength = poison.strength ?? spec?.default_strength;
    if (!strength || new DimensionalNumber(strength).toNumber() <= 0) return { kind: 'skip' };
    const damageTypeCode = poison.damage_type_code ?? spec?.damage_type_code;
    if (!damageTypeCode) return { kind: 'skip' };
    const { fire, left } = this.nextDotTurnsLeft(state.dotTurnsLeft, period);
    const next = { ...state, dotTurnsLeft: left };
    if (!fire) return { kind: 'wait', next };
    const decay = poison.decay ?? spec?.default_decay;
    const afterDecay = this.decayStrength(strength, decay);
    const remaining = afterDecay ? { ...next, poison: { ...poison, strength: afterDecay } } : null;

    return {
      kind: 'tick',
      next: remaining,
      strength,
      damageTypeCode,
      label: template?.name ?? poisoningRule.name,
    };
  }

  advanceDotState(state: CharacterStateValue, rules: Rule[]): DotAdvance {
    const rule = rules.find((item) => item.id === state.stateRuleId && item.type === 'state');
    if (!rule) return { kind: 'skip' };
    if (rule.code === BURNING_STATE_CODE) {
      const spec = this.asStateSpec(rule);
      if (!spec) return { kind: 'skip' };

      return this.burningDot(state, rule, spec);
    }
    if (rule.code === POISONING_STATE_CODE) return this.poisoningDot(state, rule, rules);

    return { kind: 'skip' };
  }
}
