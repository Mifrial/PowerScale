import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { resolveCheckAttachedRuleCodes } from '@/modules/Roleplay/Rule/Utils/checkResolution';
import { checkSuccessRating } from '@/modules/Roleplay/Rule/Utils/checkSuccessRating';
import { advantageEntries } from '@/modules/Roleplay/Rule/Utils/aggregateSourceDeltas';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';

export function successesOf(result: DiceRollResult): DimensionalNumberValue {
  return { base: result.totalSuccesses, size: result.spec.dieSize || 0 };
}

/** Пул именованной проверки: база характеристики, преимущества вручную. */
export function namedCheckSpec(
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
    advantages: advantageEntries(adv),
    dieSize: value.size,
    poolSize: value.size,
    efficiencySize: 0,
    label,
    actorKey,
  };
}

/** Навесить слой проверки (РУ) на уже посчитанный бросок. */
export function withCheckOutcome(
  result: DiceRollResult,
  checkCode: string,
  difficulty: DimensionalNumberValue,
): DiceRollResult {
  const outcome = checkSuccessRating(successesOf(result), difficulty);

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
export function rollNamedCheck(
  spec: DiceRollSpec,
  checkCode: string,
  difficulty: DimensionalNumberValue,
  rng: DiceRng,
  rules: Rule[],
  mechanics: Mechanic[],
): DiceRollResult {
  const attachedRuleCodes = resolveCheckAttachedRuleCodes(checkCode, rules);
  const rolled = rollEngine.roll(spec, rng, rules, mechanics, attachedRuleCodes, []);

  return withCheckOutcome(rolled, checkCode, difficulty);
}

export interface JointCheckRoll {
  left: DiceRollResult;
  right: DiceRollResult;
}

/**
 * Pairwise: чужой размерный итог = сложность стороны. Identity — один checkCode.
 */
export function rollJointCheck(
  leftSpec: DiceRollSpec,
  rightSpec: DiceRollSpec,
  checkCode: string,
  rng: DiceRng,
  rules: Rule[],
  mechanics: Mechanic[],
): JointCheckRoll {
  const attachedRuleCodes = resolveCheckAttachedRuleCodes(checkCode, rules);
  const leftRolled = rollEngine.roll(leftSpec, rng, rules, mechanics, attachedRuleCodes, []);
  const rightRolled = rollEngine.roll(rightSpec, rng, rules, mechanics, attachedRuleCodes, []);

  return {
    left: withCheckOutcome(leftRolled, checkCode, successesOf(rightRolled)),
    right: withCheckOutcome(rightRolled, checkCode, successesOf(leftRolled)),
  };
}
