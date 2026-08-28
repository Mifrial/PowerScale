import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export type ActionCostAmount =
  | DimensionalNumberValue
  | number
  | {
      type: 'chosen';
      max: 'available';
    };
