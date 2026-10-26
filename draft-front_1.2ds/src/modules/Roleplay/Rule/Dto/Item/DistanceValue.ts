import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';

/** Дистанция в шагах или ипари: формула вычисляется в размерное число. */
export interface DistanceValue {
  type: 'step' | 'ipari';
  value: DimensionalFormula;
}

/** Нижняя и верхняя границы дистанции профиля. */
export interface DistanceRange {
  min: DistanceValue;
  max: DistanceValue | null;
}
