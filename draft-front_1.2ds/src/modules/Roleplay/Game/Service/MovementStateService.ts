import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { ActionResolution } from '@/modules/Roleplay/Game/Dto/ActionResolution';
import type { CurrentSpeed } from '@/modules/Roleplay/Game/Dto/CurrentSpeed';

export class MovementStateService {
  zero(): CurrentSpeed {
    return {
      horizontal: { stepsPerActionPoint: 0, direction: null },
      vertical: { stepsPerActionPoint: 0, direction: null },
    };
  }

  fromResolution(resolution: ActionResolution, currentMovementStep: DimensionalNumberValue): CurrentSpeed {
    const speed = this.zero();
    if (resolution.spentActionPoints <= 0) return speed;

    speed.horizontal = this.componentSpeed(
      resolution.movement.horizontal.traversedDistance,
      currentMovementStep,
      resolution.movement.horizontal.direction,
      resolution.spentActionPoints,
    ) as CurrentSpeed['horizontal'];
    speed.vertical = this.componentSpeed(
      resolution.movement.vertical.traversedDistance,
      currentMovementStep,
      resolution.movement.vertical.direction,
      resolution.spentActionPoints,
    ) as CurrentSpeed['vertical'];

    return speed;
  }

  private componentSpeed(
    distance: DimensionalNumberValue,
    currentMovementStep: DimensionalNumberValue,
    direction: CurrentSpeed['horizontal']['direction'] | CurrentSpeed['vertical']['direction'],
    actionPoints: number,
  ): {
    stepsPerActionPoint: number;
    direction: typeof direction;
  } {
    const distanceValue = new DimensionalNumber(distance);
    if (distanceValue.compare(new DimensionalNumber({ base: 0, size: 0 })) <= 0) {
      return { stepsPerActionPoint: 0, direction: null };
    }

    return {
      stepsPerActionPoint: Math.floor(
        distanceValue.divideFloor(new DimensionalNumber(currentMovementStep)) / actionPoints,
      ),
      direction,
    };
  }
}
