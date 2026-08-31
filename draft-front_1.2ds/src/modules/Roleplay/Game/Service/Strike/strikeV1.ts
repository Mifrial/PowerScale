import type { StrikeProcedure } from '@/modules/Roleplay/Game/Dto/StrikeProcedure';
import { STRIKE_PROCEDURE_MECHANIC_CODE, STRIKE_PROCEDURE_VERSION_1 } from '@/modules/Roleplay/Rule/init';

/** Процедура удара 1.0.0 — выгрузка «Ближний бой: нанесение удара / защита». */
export const strikeV1: StrikeProcedure = {
  code: STRIKE_PROCEDURE_MECHANIC_CODE,
  version: STRIKE_PROCEDURE_VERSION_1,
  ignoreDefense: { base: 0, size: -1 },
  dodgeEfficiency: { base: 4, size: -1 },
  minBlockEfficiency: { base: 4, size: -1 },
};
