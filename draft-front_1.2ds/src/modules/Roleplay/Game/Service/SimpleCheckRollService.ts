import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_SIMPLE_CODE } from '@/modules/Roleplay/Rule/init';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';

import { SIMPLE_CHECK_ZERO_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Check/SIMPLE_CHECK_ZERO_DIFFICULTY';
export class SimpleCheckRollService {
  /** Навесить исход простой проверки против {0|0} на уже посчитанный бросок. */
  withSimpleCheckZero(result: DiceRollResult, checkCode = CHECK_SIMPLE_CODE): DiceRollResult {
    if (result.check) return result;

    return checkRollService.withCheckOutcome(result, checkCode, SIMPLE_CHECK_ZERO_DIFFICULTY);
  }

  /** Чат-бросок: механики простой проверки + сложность {0|0}. */
  rollSimpleCheckZero(spec: DiceRollSpec, rng: DiceRng, rules: Rule[], mechanics: Mechanic[]): DiceRollResult {
    const attachedRuleCodes = checkResolutionService.resolveCheckAttachedRuleCodes(CHECK_SIMPLE_CODE, rules);
    const rolled = rollEngine.roll(spec, rng, rules, mechanics, attachedRuleCodes, []);

    return this.withSimpleCheckZero(rolled);
  }
}
