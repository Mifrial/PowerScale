import type { MechanicHandler } from '@/modules/Roleplay/Rule/Interface/MechanicHandler';
import type { MovementStateMechanicContext } from '@/modules/Roleplay/Rule/Dto/MovementStateMechanicContext';
import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';

export class MovementStateMechanic implements MechanicHandler<MovementStateMechanicContext> {
  readonly code = 'movement_state';
  readonly version = '1.0.0';
  readonly subscriptions = { action_resolved: 100 };

  run(input: { payload: MechanicPayload | null; context: MovementStateMechanicContext; event: string }): void {
    input.context.currentSpeed = input.context.resolveCurrentSpeed(
      input.context.resolution,
      input.context.currentMovementStep,
    );
  }
}
