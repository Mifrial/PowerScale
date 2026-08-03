import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';

export type SpellDuration =
  | { type: 'instant' }
  | {
      type: 'refreshable' | 'sustained';
      difficulty: DimensionalNumberValue;
      action_cost: DimensionalNumberValue | number;
      limit?: { value: DimensionalNumberValue | number; unit: 'turn' | 'minute' | 'hour' };
    };
