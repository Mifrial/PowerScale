import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export type MovementDistanceExpression =
  | { type: 'steps'; count: number }
  | { type: 'literal'; value: DimensionalNumberValue }
  | { type: 'current_movement_step'; multiplier: number }
  | {
      type: 'size_gap_times_step';
      characteristic_code_from: string;
      characteristic_code_to: string;
      base_steps: number;
      gap_multiplier: number;
    }
  | { type: 'change_size'; expression: MovementDistanceExpression; size_delta: number }
  | { type: 'add'; expressions: MovementDistanceExpression[] };
