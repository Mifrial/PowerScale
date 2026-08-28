import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ActionOperationRequest } from '@/modules/Roleplay/Game/Dto/ActionOperationRequest';

export interface ISpatialResolver {
  resolveMovement(request: NonNullable<ActionOperationRequest['movement']>): {
    horizontal?: { traversedDistance: DimensionalNumberValue };
    vertical?: { traversedDistance: DimensionalNumberValue };
    status: 'completed' | 'collision';
  };
}
