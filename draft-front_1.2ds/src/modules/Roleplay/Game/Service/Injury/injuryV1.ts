import type { InjuryProcedure } from '@/modules/Roleplay/Game/Dto/InjuryProcedure';
import {
  INJURY_PROCEDURE_MECHANIC_CODE,
  INJURY_PROCEDURE_VERSION_1,
} from '@/modules/Roleplay/Rule/Constant/Combat/INJURY_PROCEDURE';

export const injuryV1: InjuryProcedure = {
  code: INJURY_PROCEDURE_MECHANIC_CODE,
  version: INJURY_PROCEDURE_VERSION_1,
  explodeFace: 6,
  dropBelow: 3,
  woundDiceDivisor: 2,
  exhaustionCheckMin: 6,
  exhaustionDiceOffset: 5,
};
