import type { ActionCharacteristicModifier } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCharacteristicModifier';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/**
 * Формула размерного поля.
 * Параметр способности сюда не входит: размерный параметр живёт в SpellValue
 * и в гранте characteristic_parameter.
 */
export type DimensionalFormula =
  | { type: 'fixed'; value: number }
  | { type: 'dimensional'; base: number; size: number }
  | { type: 'literal'; value: DimensionalNumberValue }
  | { type: 'steps'; count: number }
  | { type: 'current_movement_step'; multiplier: number }
  | {
      type: 'size_gap_times_step';
      characteristic_code_from: string;
      characteristic_code_to: string;
      base_steps: number;
      gap_multiplier: number;
    }
  | { type: 'change_size'; expression: DimensionalFormula; size_delta: number }
  | { type: 'add'; expressions: DimensionalFormula[] }
  | { type: 'characteristic'; characteristic_code: string; modifier: number }
  | {
      type: 'actionCharacteristic';
      action: 'strike' | 'throw' | 'shoot';
      characteristic: string;
      modifier: ActionCharacteristicModifier[];
      multiplier?: number;
    };
