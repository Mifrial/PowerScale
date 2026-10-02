import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StrikeProcedure } from '@/modules/Roleplay/Game/Dto/StrikeProcedure';
import { strikeProcedureRegistry } from '@/modules/Roleplay/Game/Service/Strike/Instance/strikeProcedureRegistry';
import { shootV1 } from '@/modules/Roleplay/Game/Service/Strike/shootV1';
import { strikeV1 } from '@/modules/Roleplay/Game/Service/Strike/strikeV1';
import { throwV1 } from '@/modules/Roleplay/Game/Service/Strike/throwV1';
import {
  SHOOT_PROCEDURE_MECHANIC_CODE,
  STRIKE_PROCEDURE_MECHANIC_CODE,
  THROW_PROCEDURE_MECHANIC_CODE,
} from '@/modules/Roleplay/Rule/Constant/Combat/HIT_PROCEDURE';

function resolveByMechanic(
  mechanicCode: string,
  fallback: StrikeProcedure,
  rules: Rule[],
  mechanics: Mechanic[],
): StrikeProcedure {
  const rule = rules.find((candidate) =>
    candidate.mechanics.some(
      (entry) => mechanics.find((mechanic) => mechanic.id === entry.mechanicId)?.code === mechanicCode,
    ),
  );
  if (!rule) return fallback;
  const row = rule.mechanics.find(
    (entry) => mechanics.find((mechanic) => mechanic.id === entry.mechanicId)?.code === mechanicCode,
  );
  const mechanic = row ? mechanics.find((entry) => entry.id === row.mechanicId) : undefined;
  if (!mechanic) return fallback;

  return strikeProcedureRegistry.resolve(mechanic.code, mechanic.version) ?? fallback;
}

/** Процедура удара: первая карточка ревизии с механикой `strike`, иначе v1. */
export function resolveStrikeProcedure(rules: Rule[], mechanics: Mechanic[]): StrikeProcedure {
  return resolveByMechanic(STRIKE_PROCEDURE_MECHANIC_CODE, strikeV1, rules, mechanics);
}

export function resolveHitProcedure(
  profileType: 'strike' | 'throw' | 'shoot',
  rules: Rule[],
  mechanics: Mechanic[],
): StrikeProcedure {
  if (profileType === 'throw') {
    return resolveByMechanic(THROW_PROCEDURE_MECHANIC_CODE, throwV1, rules, mechanics);
  }
  if (profileType === 'shoot') {
    return resolveByMechanic(SHOOT_PROCEDURE_MECHANIC_CODE, shootV1, rules, mechanics);
  }

  return resolveStrikeProcedure(rules, mechanics);
}
