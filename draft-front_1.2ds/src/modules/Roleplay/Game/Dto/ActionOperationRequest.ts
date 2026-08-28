import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type {
  HorizontalMovementDirection,
  VerticalMovementDirection,
} from '@/modules/Roleplay/Rule/Dto/Ability/MovementOperation';

export interface ActionOperationRequest {
  movement?: {
    horizontal?: {
      direction: HorizontalMovementDirection;
      distance: DimensionalNumberValue;
    };
    vertical?: {
      direction: VerticalMovementDirection;
      distance: DimensionalNumberValue;
    };
  };
}
