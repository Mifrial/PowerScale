import type { ScalarFormula } from '@/modules/Roleplay/Rule/Dto/Ability/ScalarFormula';

/** Узлы, которые редактор предлагает в скалярном поле. */
export const SCALAR_FORMULA_MODES: ScalarFormula['type'][] = [
  'fixed',
  'parameter',
  'parameter_floor_div',
  'ability_level',
  'to_scalar',
  'characteristic_size',
  'characteristic_size_positive',
  'characteristic_size_gap',
];
