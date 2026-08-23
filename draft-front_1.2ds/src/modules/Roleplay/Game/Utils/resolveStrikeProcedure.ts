import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StrikeProcedure } from '@/modules/Roleplay/Game/Dto/StrikeProcedure';
import { strikeProcedureRegistry } from '@/modules/Roleplay/Game/Service/Strike/Instance/strikeProcedureRegistry';
import { strikeV1 } from '@/modules/Roleplay/Game/Service/Strike/strikeV1';
import {
  STRIKE_PROCEDURE_MECHANIC_CODE,
  STRIKE_PROCEDURE_RULE_CODE,
  STRIKE_PROCEDURE_VERSION_1,
} from '@/modules/Roleplay/Rule/Constant/Combat/STRIKE_PROCEDURE';

/**
 * Процедура удара из среза ревизии: карточка `strike-procedure` → mechanic code@version.
 * Нет карточки / неизвестная версия → v1 (текущий канон).
 */
export function resolveStrikeProcedure(rules: Rule[], mechanics: Mechanic[]): StrikeProcedure {
  const rule = rules.find((candidate) => candidate.code === STRIKE_PROCEDURE_RULE_CODE);
  const mechanic = rule?.mechanicId != null ? mechanics.find((entry) => entry.id === rule.mechanicId) : undefined;
  const code = mechanic?.code ?? STRIKE_PROCEDURE_MECHANIC_CODE;
  const version = mechanic?.version ?? STRIKE_PROCEDURE_VERSION_1;

  return strikeProcedureRegistry.resolve(code, version) ?? strikeV1;
}
