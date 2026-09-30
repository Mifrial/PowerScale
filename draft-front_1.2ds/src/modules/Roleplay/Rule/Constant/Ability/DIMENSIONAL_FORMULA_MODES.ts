import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';

/** Узлы размерного поля без параметра способности. Параметр добавляется, когда он объявлен. */
export const DIMENSIONAL_FORMULA_MODES: Exclude<DimensionalFormula['type'], 'parameter'>[] = [
  'fixed',
  'characteristic',
  'dimensional',
  'actionCharacteristic',
];
