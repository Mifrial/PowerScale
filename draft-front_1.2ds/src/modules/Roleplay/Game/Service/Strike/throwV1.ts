import type { StrikeProcedure } from '@/modules/Roleplay/Game/Dto/StrikeProcedure';
import {
  THROW_PROCEDURE_MECHANIC_CODE,
  HIT_PROCEDURE_VERSION_1,
} from '@/modules/Roleplay/Rule/Constant/Combat/HIT_PROCEDURE';

/** Бросок 1.0.0: база игнора 1↓ (укрытие 0, результат защиты 0). */
export const throwV1: StrikeProcedure = {
  code: THROW_PROCEDURE_MECHANIC_CODE,
  version: HIT_PROCEDURE_VERSION_1,
  ignoreDefense: { base: 1, size: -1 },
  dodgeEfficiency: { base: 4, size: -1 },
  minBlockEfficiency: { base: 4, size: -1 },
};
