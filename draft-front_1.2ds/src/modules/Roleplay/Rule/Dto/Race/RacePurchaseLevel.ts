import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';

/** Уровень закупки характеристики: «за N ОС → значение» (пропуски просто не перечисляются). */
export interface RacePurchaseLevel {
  cost: number;
  value: DimensionalNumberValue;
}
