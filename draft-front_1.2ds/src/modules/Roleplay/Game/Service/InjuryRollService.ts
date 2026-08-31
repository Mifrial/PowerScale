import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type {
  InjuryDifficultyBreakdown,
  InjuryHealRoll,
  InjuryHealUnit,
  InjuryOutcome,
} from '@/modules/Roleplay/Game/Dto/InjuryOutcome';
import type { InjuryProcedure } from '@/modules/Roleplay/Game/Dto/InjuryProcedure';
import type { DamageTypeHook } from '@/modules/Roleplay/Game/Dto/DamageTypeHook';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_INJURY_CODE } from '@/modules/Roleplay/Rule/init';
import { ADVANTAGE_SOURCE_MANUAL } from '@/modules/Roleplay/Rule/init';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';

import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';

import type { InjuryRollInput } from '@/modules/Roleplay/Game/Dto/InjuryRollInput';
export class InjuryRollService {
  injuryDifficultyBreakdown(
    input: Pick<
      InjuryRollInput,
      'leftoverDamage' | 'woundStrength' | 'endurance' | 'exhaustion' | 'attackSr' | 'difficulty'
    >,
    procedure: InjuryProcedure,
    extraDifficulty: number,
  ): InjuryDifficultyBreakdown {
    const extra = Math.max(0, extraDifficulty);
    const endurance = Math.max(1, Math.floor(input.endurance));
    const woundDivisor = procedure.woundDivisor > 0 ? procedure.woundDivisor : 2;
    const exhaustionMin = procedure.exhaustionCheckMin > 0 ? procedure.exhaustionCheckMin : 7;
    const exhaustionOffset =
      typeof procedure.exhaustionDifficultyOffset === 'number' && procedure.exhaustionDifficultyOffset >= 0
        ? procedure.exhaustionDifficultyOffset
        : 6;
    const leftoverDamage = Math.max(0, input.leftoverDamage);
    const woundStrength = Math.max(0, input.woundStrength);
    const exhaustion = Math.max(0, input.exhaustion);
    if (input.difficulty !== undefined) {
      return {
        leftoverDamage,
        endurance,
        fromDamage: 0,
        woundStrength,
        woundDivisor,
        fromWound: 0,
        exhaustion,
        exhaustionOffset,
        fromExhaustion: 0,
        extraDifficulty: extra,
        source: 'manual',
        total: Math.max(0, Math.floor(input.difficulty) + extra),
      };
    }
    const fromDamage = Math.floor(leftoverDamage / endurance);
    const fromWound = Math.floor(woundStrength / woundDivisor);
    const fromExhaustion = exhaustion >= exhaustionMin ? Math.max(0, exhaustion - exhaustionOffset) : 0;
    let source: InjuryDifficultyBreakdown['source'] = 'leftover';
    let best = fromDamage;
    if (fromWound > best) {
      best = fromWound;
      source = 'wound';
    }
    if (fromExhaustion > best) {
      best = fromExhaustion;
      source = 'exhaustion';
    }

    return {
      leftoverDamage,
      endurance,
      fromDamage,
      woundStrength,
      woundDivisor,
      fromWound,
      exhaustion,
      exhaustionOffset,
      fromExhaustion,
      extraDifficulty: extra,
      source,
      total: Math.max(0, best + extra),
    };
  }

  injuryDifficulty(
    input: Pick<
      InjuryRollInput,
      'leftoverDamage' | 'woundStrength' | 'endurance' | 'exhaustion' | 'attackSr' | 'difficulty'
    >,
    procedure: InjuryProcedure,
    extraDifficulty: number,
  ): number {
    return this.injuryDifficultyBreakdown(input, procedure, extraDifficulty).total;
  }

  private rollDie(rng: DiceRng, faces: number): number {
    return Math.floor(rng() * faces) + 1;
  }

  private healSpec(strength: number): { diceCount: number; dieFaces: number; unit: InjuryHealUnit } {
    if (strength <= 1) return { diceCount: 1, dieFaces: 6, unit: 'days' };
    if (strength === 2) return { diceCount: 2, dieFaces: 6, unit: 'days' };
    if (strength === 3) return { diceCount: 4, dieFaces: 6, unit: 'days' };
    if (strength === 4) return { diceCount: 1, dieFaces: 6, unit: 'decades' };
    if (strength === 5) return { diceCount: 1, dieFaces: 5, unit: 'months' };

    return { diceCount: 1, dieFaces: 6, unit: 'years' };
  }

  private flagsOf(faces: number[]): Pick<InjuryOutcome, 'permanent' | 'lethal' | 'disfiguring'> {
    const fours = faces.filter((face) => face === 4).length;
    const fives = faces.filter((face) => face === 5).length;
    const sixes = faces.filter((face) => face === 6).length;

    return {
      lethal: sixes >= 3,
      permanent: sixes >= 2 && fives >= 1,
      disfiguring: sixes >= 2 && fours >= 1,
    };
  }

  private extraDifficultyFromHooks(hooks: DamageTypeHook[], attackSr: number): number {
    let extra = 0;
    for (const hook of hooks) {
      if (hook.extraDiceFromSrDivisor) {
        extra += Math.floor(Math.max(0, attackSr) / hook.extraDiceFromSrDivisor);
      }
    }

    return extra;
  }

  private slashingHindrance(hooks: DamageTypeHook[]): AdvantageModifier[] {
    const delta = hooks.reduce((sum, hook) => sum + (hook.efficiencyDelta ?? 0), 0);
    if (delta >= 0) return [];

    return [{ source_code: 'damage-type-injury', source_label: 'тип урона', delta }];
  }

  private emptyInjury(difficulty: number, rating: number, breakdown?: InjuryDifficultyBreakdown): InjuryOutcome {
    return {
      strength: 0,
      permanent: false,
      temporary: false,
      lethal: false,
      disfiguring: false,
      difficulty,
      rating,
      breakdown,
    };
  }

  rollInjury(input: InjuryRollInput, rng: DiceRng, rules: Rule[], mechanics: Mechanic[]): DiceRollResult {
    const procedure = resolveInjuryProcedure(rules, mechanics);
    const hooks = damageTypeHooksService.injuryHooksOf(
      damageTypeHooksService.resolveDamageTypeHooks(input.damageTypeCode, rules, mechanics),
    );
    const extraDifficulty = this.extraDifficultyFromHooks(hooks, input.attackSr);
    const breakdown = this.injuryDifficultyBreakdown(input, procedure, extraDifficulty);
    const difficulty = breakdown.total;
    const advantages = [...(input.advantages ?? []), ...this.slashingHindrance(hooks)];
    const defaults = rollPoolDefaults(rules);
    const label = input.label ?? 'Проверка на увечье';
    const specBase = {
      diceCount: procedure.poolDice,
      dieSize: 0,
      dieFaces: defaults.dieFaces,
      efficiency: defaults.efficiency,
      advantages,
      label,
      actorKey: input.actorKey,
    };

    if (difficulty <= 0) {
      return {
        spec: specBase,
        rolls: [],
        successes: [],
        adjustedRolls: [],
        droppedRolls: [],
        totalSuccesses: 0,
        check: {
          check_code: CHECK_INJURY_CODE,
          difficulty: { base: 0, size: 0 },
          passed: true,
          rating: 0,
        },
        injury: this.emptyInjury(0, 0, breakdown),
      };
    }

    const attached = checkResolutionService.resolveCheckAttachedRuleCodes(CHECK_INJURY_CODE, rules);
    const rolled = rollEngine.roll(specBase, rng, rules, mechanics, attached, []);
    const rating = rolled.totalSuccesses - difficulty;
    const passed = rating >= 0;
    const strength = passed ? 0 : -rating;
    const flags = passed ? { permanent: false, lethal: false, disfiguring: false } : this.flagsOf(rolled.adjustedRolls);
    let heal: InjuryHealRoll | undefined;
    if (!flags.permanent && strength > 0) {
      const spec = this.healSpec(strength);
      const rolls = Array.from({ length: spec.diceCount }, () => this.rollDie(rng, spec.dieFaces));
      heal = {
        diceCount: spec.diceCount,
        dieFaces: spec.dieFaces,
        unit: spec.unit,
        rolls,
        total: rolls.reduce((sum, value) => sum + value, 0),
      };
    }
    const injury: InjuryOutcome = {
      strength,
      permanent: flags.permanent,
      temporary: strength > 0 && !flags.permanent,
      lethal: flags.lethal,
      disfiguring: flags.disfiguring,
      heal,
      difficulty,
      rating,
      breakdown,
    };

    return {
      ...rolled,
      check: {
        check_code: CHECK_INJURY_CODE,
        difficulty: { base: difficulty, size: 0 },
        passed,
        rating,
      },
      injury,
    };
  }

  manualInjuryAdvantages(delta: number): AdvantageModifier[] {
    if (delta === 0) return [];

    return [{ source_code: ADVANTAGE_SOURCE_MANUAL, source_label: 'вручную', delta }];
  }
}
