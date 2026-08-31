import type { InjuryProcedure } from '@/modules/Roleplay/Game/Dto/InjuryProcedure';
import { INJURY_PROCEDURE_MECHANIC_CODE, INJURY_PROCEDURE_VERSION_1 } from '@/modules/Roleplay/Rule/init';

export const injuryV1: InjuryProcedure = {
  code: INJURY_PROCEDURE_MECHANIC_CODE,
  version: INJURY_PROCEDURE_VERSION_1,
  poolDice: 4,
  woundDivisor: 2,
  exhaustionCheckMin: 7,
  exhaustionDifficultyOffset: 6,
};
