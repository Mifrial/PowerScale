import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type {
  HorizontalMovementDirection,
  VerticalMovementDirection,
} from '@/modules/Roleplay/Rule/Dto/Ability/MovementOperation';

export type ActionResolutionStatus = 'completed' | 'interrupted' | 'failed';
export type ActionInterruptionReason = 'collision' | 'normal' | 'emergency' | null;

export interface MovementResolutionComponent {
  direction: HorizontalMovementDirection | 'mixed' | null;
  requestedDistance: DimensionalNumberValue;
  traversedDistance: DimensionalNumberValue;
}

export interface ActionResolution {
  status: ActionResolutionStatus;
  spentActionPoints: number;
  operationResults: {
    type: 'movement' | 'turn' | 'posture';
    status: ActionResolutionStatus;
  }[];
  movement: {
    horizontal: MovementResolutionComponent;
    vertical: {
      direction: VerticalMovementDirection | 'mixed' | null;
      requestedDistance: DimensionalNumberValue;
      traversedDistance: DimensionalNumberValue;
    };
  };
  interruptionReason?: ActionInterruptionReason;
}
