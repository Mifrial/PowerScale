import type { ActionCharacteristicModifier } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCharacteristicModifier';

/**
 * Формула размерного поля.
 * Параметр способности сюда не входит: размерный параметр живёт в SpellValue
 * и в гранте characteristic_parameter.
 */
export type DimensionalFormula =
  | { type: 'fixed'; value: number }
  | { type: 'dimensional'; base: number; size: number }
  | { type: 'characteristic'; characteristic_code: string; modifier: number }
  | {
      type: 'actionCharacteristic';
      action: 'strike' | 'throw' | 'shoot';
      characteristic: string;
      modifier: ActionCharacteristicModifier[];
      multiplier?: number;
    };
