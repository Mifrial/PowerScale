import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { InjuryProcedure } from '@/modules/Roleplay/Game/Dto/InjuryProcedure';
import { injuryProcedureRegistry } from '@/modules/Roleplay/Game/Service/Injury/Instance/injuryProcedureRegistry';
import { injuryV1 } from '@/modules/Roleplay/Game/Service/Injury/injuryV1';
import {
  INJURY_PROCEDURE_MECHANIC_CODE,
  INJURY_PROCEDURE_RULE_CODE,
  INJURY_PROCEDURE_VERSION_1,
} from '@/modules/Roleplay/Rule/init';

/** Карточка `injury-procedure` → mechanic code@version; иначе v1. */
export function resolveInjuryProcedure(rules: Rule[], mechanics: Mechanic[]): InjuryProcedure {
  const rule = rules.find((candidate) => candidate.code === INJURY_PROCEDURE_RULE_CODE);
  const mechanic = rule?.mechanicId != null ? mechanics.find((entry) => entry.id === rule.mechanicId) : undefined;
  const code = mechanic?.code ?? INJURY_PROCEDURE_MECHANIC_CODE;
  const version = mechanic?.version ?? INJURY_PROCEDURE_VERSION_1;

  return injuryProcedureRegistry.resolve(code, version) ?? injuryV1;
}
