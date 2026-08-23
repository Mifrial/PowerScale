import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_SIMPLE_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { resolveCheckAttachedRuleCodes } from '@/modules/Roleplay/Rule/Utils/checkResolution';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { withCheckOutcome } from '@/modules/Roleplay/Game/Utils/checkRoll';

export const SIMPLE_CHECK_ZERO_DIFFICULTY = { base: 0, size: 0 };

/** Навесить исход простой проверки против {0|0} на уже посчитанный бросок. */
export function withSimpleCheckZero(result: DiceRollResult, checkCode = CHECK_SIMPLE_CODE): DiceRollResult {
  if (result.check) return result;

  return withCheckOutcome(result, checkCode, SIMPLE_CHECK_ZERO_DIFFICULTY);
}

/** Чат-бросок: механики простой проверки + сложность {0|0}. */
export function rollSimpleCheckZero(
  spec: DiceRollSpec,
  rng: DiceRng,
  rules: Rule[],
  mechanics: Mechanic[],
): DiceRollResult {
  const attachedRuleCodes = resolveCheckAttachedRuleCodes(CHECK_SIMPLE_CODE, rules);
  const rolled = rollEngine.roll(spec, rng, rules, mechanics, attachedRuleCodes, []);

  return withSimpleCheckZero(rolled);
}
