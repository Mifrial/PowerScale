import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';

/** Формула скалярного поля. */
export type ScalarFormula =
  | { type: 'fixed'; value: number }
  | { type: 'parameter'; parameter_code: string; per_unit: number }
  | { type: 'parameter_floor_div'; parameter_code: string; divisor: number }
  | { type: 'ability_level'; ability_code: string; multiplier?: number; offset?: number }
  /** База размерного значения после переноса на средний размер. */
  | { type: 'to_scalar'; value: DimensionalFormula }
  | { type: 'characteristic_size'; characteristic_code: string }
  | { type: 'characteristic_size_positive'; characteristic_code: string }
  | {
      type: 'characteristic_size_gap';
      characteristic_code_from: string;
      characteristic_code_to: string;
    };
