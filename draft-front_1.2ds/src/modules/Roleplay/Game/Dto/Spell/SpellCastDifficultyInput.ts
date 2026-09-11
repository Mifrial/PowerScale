import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Вход формулы Сложности сотворения (уже разрешённые мощь/контроль и сопротивление). */
export interface SpellCastDifficultyInput {
  requiredPower: DimensionalNumberValue;
  usedPower: DimensionalNumberValue;
  requiredControl: DimensionalNumberValue;
  availableControl: DimensionalNumberValue;
  /** Сопротивление типа с `modifies_spell_difficulty`, если каст направлен на цель. */
  resistanceModify?: number;
}
