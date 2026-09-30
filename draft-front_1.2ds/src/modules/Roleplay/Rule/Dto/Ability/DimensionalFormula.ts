import type { ActionCharacteristicModifier } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCharacteristicModifier';

/**
 * Формула размерного поля.
 * Параметр здесь без per_unit: множитель единицы есть только у скалярного параметра.
 */
export type DimensionalFormula =
  | { type: 'fixed'; value: number }
  | { type: 'dimensional'; base: number; size: number }
  | { type: 'characteristic'; characteristic_code: string; modifier: number }
  | { type: 'parameter'; parameter_code: string }
  | {
      type: 'actionCharacteristic';
      action: 'strike' | 'throw' | 'shoot';
      characteristic: string;
      modifier: ActionCharacteristicModifier[];
      multiplier?: number;
    };
