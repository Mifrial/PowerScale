import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Итог формулы Сложности сотворения. */
export interface SpellCastDifficultyResult {
  difficulty: DimensionalNumberValue;
  needsCheck: boolean;
  powerShortage: number;
  controlShortage: number;
}
