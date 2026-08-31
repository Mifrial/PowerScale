import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import { CHECK_HIT_CODE } from '@/modules/Roleplay/Rule/init';
import { checkSuccessRatingService } from '@/modules/Roleplay/Rule/init';
import { HIT_MIN_SUCCESS_SIZE } from '@/modules/Roleplay/Rule/init';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';

import type { JointCheckRoll } from '@/modules/Roleplay/Game/Dto/JointCheckRoll';
export class CheckRollService {
  successesOf(result: DiceRollResult): DimensionalNumberValue {
    return DimensionalNumber.from({ base: result.totalSuccesses, size: result.spec.dieSize || 0 }).foldNegativeBase()
      .value;
  }

  /** Пул именованной проверки: база характеристики, преимущества вручную. */
  namedCheckSpec(
    label: string,
    value: DimensionalNumberValue,
    adv: number,
    rules: Rule[],
    actorKey?: CombatEntityKey,
  ): DiceRollSpec {
    const defaults = rollPoolDefaults(rules);

    return {
      diceCount: Math.max(1, value.base),
      dieFaces: defaults.dieFaces,
      efficiency: defaults.efficiency,
      advantages: aggregateSourceDeltasService.advantageEntries(adv),
      dieSize: value.size,
      poolSize: value.size,
      efficiencySize: 0,
      label,
      actorKey,
    };
  }

  /** Навесить слой проверки (РУ) на уже посчитанный бросок. */
  withCheckOutcome(result: DiceRollResult, checkCode: string, difficulty: DimensionalNumberValue): DiceRollResult {
    const outcome = checkSuccessRatingService.checkSuccessRating(this.successesOf(result), difficulty, {
      minSize: checkCode === CHECK_HIT_CODE ? HIT_MIN_SUCCESS_SIZE : undefined,
    });

    return {
      ...result,
      check: {
        check_code: checkCode,
        difficulty,
        passed: outcome.passed,
        rating: outcome.rating,
      },
    };
  }

  /** Именованная проверка: механики с карточки check, сложность задана. */
  rollNamedCheck(
    spec: DiceRollSpec,
    checkCode: string,
    difficulty: DimensionalNumberValue,
    rng: DiceRng,
    rules: Rule[],
    mechanics: Mechanic[],
  ): DiceRollResult {
    const attachedRuleCodes = checkResolutionService.resolveCheckAttachedRuleCodes(checkCode, rules);
    const rolled = rollEngine.roll(spec, rng, rules, mechanics, attachedRuleCodes, []);

    return this.withCheckOutcome(rolled, checkCode, difficulty);
  }

  /**
   * Pairwise: чужой размерный итог = сложность стороны. Identity — один checkCode.
   */
  rollJointCheck(
    leftSpec: DiceRollSpec,
    rightSpec: DiceRollSpec,
    checkCode: string,
    rng: DiceRng,
    rules: Rule[],
    mechanics: Mechanic[],
  ): JointCheckRoll {
    const attachedRuleCodes = checkResolutionService.resolveCheckAttachedRuleCodes(checkCode, rules);
    const leftRolled = rollEngine.roll(leftSpec, rng, rules, mechanics, attachedRuleCodes, []);
    const rightRolled = rollEngine.roll(rightSpec, rng, rules, mechanics, attachedRuleCodes, []);

    return {
      left: this.withCheckOutcome(leftRolled, checkCode, this.successesOf(rightRolled)),
      right: this.withCheckOutcome(rightRolled, checkCode, this.successesOf(leftRolled)),
    };
  }
}
