import type {
  HorizontalMovementDirection,
  VerticalMovementDirection,
} from '@/modules/Roleplay/Rule/Dto/Ability/MovementOperation';

export type CurrentHorizontalDirection = HorizontalMovementDirection | 'mixed' | null;
export type CurrentVerticalDirection = VerticalMovementDirection | 'mixed' | null;

export interface CurrentSpeed {
  horizontal: {
    stepsPerActionPoint: number;
    direction: CurrentHorizontalDirection;
  };
  vertical: {
    stepsPerActionPoint: number;
    direction: CurrentVerticalDirection;
  };
}
