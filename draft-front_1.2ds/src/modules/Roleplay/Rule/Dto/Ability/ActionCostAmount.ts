import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export type ActionCostAmount =
  | DimensionalNumberValue
  | number
  | {
      type: 'chosen';
      max: 'available';
    }
  | {
      /** Стоимость — N ходов: N полных лимитов ресурса (для ОД — N ходов). */
      type: 'turn';
      count: number;
    };
