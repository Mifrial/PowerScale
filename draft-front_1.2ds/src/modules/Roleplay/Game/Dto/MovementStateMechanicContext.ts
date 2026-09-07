export interface MovementStateMechanicContext {
  resolution: {
    movement: {
      horizontal: { traversedDistance: { base: number; size: number } };
      vertical: { traversedDistance: { base: number; size: number } };
    };
  };
  currentMovementStep: { base: number; size: number };
  currentSpeed: unknown;
  resolveCurrentSpeed: (resolution: unknown, movementStep: { base: number; size: number }) => unknown;
}
