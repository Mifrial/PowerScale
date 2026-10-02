import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { InjuryProcedure } from '@/modules/Roleplay/Game/Dto/InjuryProcedure';
import { injuryProcedureRegistry } from '@/modules/Roleplay/Game/Service/Injury/Instance/injuryProcedureRegistry';
import { injuryV1 } from '@/modules/Roleplay/Game/Service/Injury/injuryV1';
import {
  INJURY_PROCEDURE_MECHANIC_CODE,
} from '@/modules/Roleplay/Rule/Constant/Combat/INJURY_PROCEDURE';

/** Первая карточка ревизии с механикой `injury`, иначе v1. */
export function resolveInjuryProcedure(rules: Rule[], mechanics: Mechanic[]): InjuryProcedure {
  const rule = rules.find((candidate) =>
    candidate.mechanics.some(
      (entry) => mechanics.find((mechanic) => mechanic.id === entry.mechanicId)?.code === INJURY_PROCEDURE_MECHANIC_CODE,
    ),
  );
  if (!rule) return injuryV1;
  const row = rule.mechanics.find(
    (entry) => mechanics.find((mechanic) => mechanic.id === entry.mechanicId)?.code === INJURY_PROCEDURE_MECHANIC_CODE,
  );
  const mechanic = row ? mechanics.find((entry) => entry.id === row.mechanicId) : undefined;
  if (!mechanic) return injuryV1;

  return injuryProcedureRegistry.resolve(mechanic.code, mechanic.version) ?? injuryV1;
}
