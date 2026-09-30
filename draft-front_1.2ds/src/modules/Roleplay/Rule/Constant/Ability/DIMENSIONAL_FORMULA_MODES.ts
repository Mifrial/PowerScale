import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';

/** Узлы размерного поля. Параметр способности в этот каталог не входит. */
export const DIMENSIONAL_FORMULA_MODES: DimensionalFormula['type'][] = [
  'fixed',
  'characteristic',
  'dimensional',
  'actionCharacteristic',
];
