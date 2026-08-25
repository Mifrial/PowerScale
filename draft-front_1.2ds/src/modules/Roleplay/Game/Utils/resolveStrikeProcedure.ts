import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StrikeProcedure } from '@/modules/Roleplay/Game/Dto/StrikeProcedure';
import { strikeProcedureRegistry } from '@/modules/Roleplay/Game/Service/Strike/Instance/strikeProcedureRegistry';
import { shootV1 } from '@/modules/Roleplay/Game/Service/Strike/shootV1';
import { strikeV1 } from '@/modules/Roleplay/Game/Service/Strike/strikeV1';
import { throwV1 } from '@/modules/Roleplay/Game/Service/Strike/throwV1';
import {
  SHOOT_PROCEDURE_MECHANIC_CODE,
  SHOOT_PROCEDURE_RULE_CODE,
  STRIKE_PROCEDURE_MECHANIC_CODE,
  STRIKE_PROCEDURE_RULE_CODE,
  THROW_PROCEDURE_MECHANIC_CODE,
  THROW_PROCEDURE_RULE_CODE,
  HIT_PROCEDURE_VERSION_1,
} from '@/modules/Roleplay/Rule/Constant/Combat/HIT_PROCEDURE';

function resolveByCard(
  ruleCode: string,
  mechanicCode: string,
  fallback: StrikeProcedure,
  rules: Rule[],
  mechanics: Mechanic[],
): StrikeProcedure {
  const rule = rules.find((candidate) => candidate.code === ruleCode);
  const mechanic = rule?.mechanicId != null ? mechanics.find((entry) => entry.id === rule.mechanicId) : undefined;
  const code = mechanic?.code ?? mechanicCode;
  const version = mechanic?.version ?? HIT_PROCEDURE_VERSION_1;

  return strikeProcedureRegistry.resolve(code, version) ?? fallback;
}

/** Процедура удара из среза ревизии: карточка `strike-procedure` → mechanic strike@version. */
export function resolveStrikeProcedure(rules: Rule[], mechanics: Mechanic[]): StrikeProcedure {
  return resolveByCard(STRIKE_PROCEDURE_RULE_CODE, STRIKE_PROCEDURE_MECHANIC_CODE, strikeV1, rules, mechanics);
}

export function resolveHitProcedure(
  profileType: 'strike' | 'throw' | 'shoot',
  rules: Rule[],
  mechanics: Mechanic[],
): StrikeProcedure {
  if (profileType === 'throw') {
    return resolveByCard(THROW_PROCEDURE_RULE_CODE, THROW_PROCEDURE_MECHANIC_CODE, throwV1, rules, mechanics);
  }
  if (profileType === 'shoot') {
    return resolveByCard(SHOOT_PROCEDURE_RULE_CODE, SHOOT_PROCEDURE_MECHANIC_CODE, shootV1, rules, mechanics);
  }

  return resolveStrikeProcedure(rules, mechanics);
}
