import type { MovementDistanceExpression } from '@/modules/Roleplay/Rule/Dto/Ability/MovementDistanceExpression';

export type HorizontalMovementDirection = 'front' | 'flank' | 'rear';
export type VerticalMovementDirection = 'up' | 'down';
export type MovementDirection = HorizontalMovementDirection | VerticalMovementDirection;

export interface DistanceConstraint {
  min: MovementDistanceExpression;
  max: MovementDistanceExpression;
  minInclusive: boolean;
  maxInclusive: boolean;
}

export interface MovementOperation {
  type: 'movement';
  allowedDirections: {
    horizontal: HorizontalMovementDirection[];
    vertical: VerticalMovementDirection[];
  };
  distance: {
    horizontal?: DistanceConstraint;
    vertical?: DistanceConstraint;
  };
  freeTurn?: {
    maxDegrees: number;
    free: boolean;
  };
}
