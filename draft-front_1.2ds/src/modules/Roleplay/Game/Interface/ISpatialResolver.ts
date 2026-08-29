import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ActionOperationRequest } from '@/modules/Roleplay/Game/Dto/ActionOperationRequest';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface ISpatialResolver {
  validateAttackTargets?: (
    initiator: CombatEntityKey,
    targets: CombatEntityKey[],
  ) => { valid: boolean; message?: string };
  resolveMovement(request: NonNullable<ActionOperationRequest['movement']>): {
    horizontal?: { traversedDistance: DimensionalNumberValue };
    vertical?: { traversedDistance: DimensionalNumberValue };
    status: 'completed' | 'collision';
  };
}
