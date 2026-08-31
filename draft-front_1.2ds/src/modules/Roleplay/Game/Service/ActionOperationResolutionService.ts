import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { ActionOperation } from '@/modules/Roleplay/Rule/Dto/Ability/ActionOperation';
import type { MovementOperation } from '@/modules/Roleplay/Rule/Dto/Ability/MovementOperation';
import type { ActionOperationRequest } from '@/modules/Roleplay/Game/Dto/ActionOperationRequest';
import type { ActionResolution } from '@/modules/Roleplay/Game/Dto/ActionResolution';
import type { ISpatialResolver } from '@/modules/Roleplay/Game/Interface/ISpatialResolver';
import { movementDistanceExpressionService } from '@/modules/Roleplay/Rule/init';

export class ActionOperationResolutionService {
  constructor(
    private readonly expressionService = movementDistanceExpressionService,
    private readonly spatialResolver?: ISpatialResolver,
  ) {}

  resolve(
    operations: ActionOperation[],
    requests: ActionOperationRequest[],
    context: {
      currentMovementStep: DimensionalNumberValue;
      characteristicValues: ReadonlyMap<string, DimensionalNumberValue>;
      spentActionPoints: number;
    },
  ): ActionResolution {
    const zero = { base: 0, size: 0 };
    let horizontalRequested = new DimensionalNumber(zero);
    let horizontalTraversed = new DimensionalNumber(zero);
    let verticalRequested = new DimensionalNumber(zero);
    let verticalTraversed = new DimensionalNumber(zero);
    let horizontalDirection: string | null = null;
    let verticalDirection: string | null = null;
    let status: ActionResolution['status'] = 'completed';
    let interruptionReason: ActionResolution['interruptionReason'];
    const operationResults: ActionResolution['operationResults'] = [];

    operations.forEach((operation, index) => {
      const request = requests[index];
      if (operation.type !== 'movement') {
        operationResults.push({ type: operation.type, status: 'completed' });

        return;
      }
      const movement = this.resolveMovement(operation, request?.movement, context);
      horizontalRequested = horizontalRequested.add(new DimensionalNumber(movement.horizontal.requestedDistance));
      horizontalTraversed = horizontalTraversed.add(new DimensionalNumber(movement.horizontal.traversedDistance));
      verticalRequested = verticalRequested.add(new DimensionalNumber(movement.vertical.requestedDistance));
      verticalTraversed = verticalTraversed.add(new DimensionalNumber(movement.vertical.traversedDistance));
      horizontalDirection = this.mergeDirection(horizontalDirection, movement.horizontal.direction);
      verticalDirection = this.mergeDirection(verticalDirection, movement.vertical.direction);
      if (movement.status === 'collision') {
        status = 'interrupted';
        interruptionReason = 'collision';
      }
      operationResults.push({
        type: 'movement',
        status: movement.status === 'collision' ? 'interrupted' : 'completed',
      });
    });

    return {
      status,
      spentActionPoints: context.spentActionPoints,
      operationResults,
      movement: {
        horizontal: {
          direction: horizontalDirection as ActionResolution['movement']['horizontal']['direction'],
          requestedDistance: horizontalRequested.value,
          traversedDistance: horizontalTraversed.value,
        },
        vertical: {
          direction: verticalDirection as ActionResolution['movement']['vertical']['direction'],
          requestedDistance: verticalRequested.value,
          traversedDistance: verticalTraversed.value,
        },
      },
      interruptionReason,
    };
  }

  movementBounds(
    operation: MovementOperation,
    axis: 'horizontal' | 'vertical',
    context: {
      currentMovementStep: DimensionalNumberValue;
      characteristicValues: ReadonlyMap<string, DimensionalNumberValue>;
    },
  ): { min: DimensionalNumberValue; max: DimensionalNumberValue } | null {
    const constraint = operation.distance[axis];
    if (!constraint) return null;

    return {
      min: this.expressionService.evaluate(constraint.min, context),
      max: this.expressionService.evaluate(constraint.max, context),
    };
  }

  private resolveMovement(
    operation: MovementOperation,
    request: NonNullable<ActionOperationRequest['movement']> | undefined,
    context: {
      currentMovementStep: DimensionalNumberValue;
      characteristicValues: ReadonlyMap<string, DimensionalNumberValue>;
    },
  ): {
    status: 'completed' | 'collision';
    horizontal: {
      direction: ActionResolution['movement']['horizontal']['direction'];
      requestedDistance: DimensionalNumberValue;
      traversedDistance: DimensionalNumberValue;
    };
    vertical: {
      direction: ActionResolution['movement']['vertical']['direction'];
      requestedDistance: DimensionalNumberValue;
      traversedDistance: DimensionalNumberValue;
    };
  } {
    if (!request?.horizontal && !request?.vertical) throw new Error('Укажите хотя бы одну компоненту движения');
    const horizontal = this.component(
      operation.allowedDirections.horizontal,
      operation.distance.horizontal,
      request?.horizontal,
      context,
    );
    const vertical = this.component(
      operation.allowedDirections.vertical,
      operation.distance.vertical,
      request?.vertical,
      context,
    );
    const resolved = this.spatialResolver?.resolveMovement(request ?? {}) ?? {
      status: 'completed' as const,
      horizontal: { traversedDistance: horizontal.requestedDistance.value },
      vertical: { traversedDistance: vertical.requestedDistance.value },
    };

    return {
      status: resolved.status,
      horizontal: {
        direction: horizontal.direction as ActionResolution['movement']['horizontal']['direction'],
        requestedDistance: horizontal.requestedDistance.value,
        traversedDistance: resolved.horizontal?.traversedDistance ?? { base: 0, size: 0 },
      },
      vertical: {
        direction: vertical.direction as ActionResolution['movement']['vertical']['direction'],
        requestedDistance: vertical.requestedDistance.value,
        traversedDistance: resolved.vertical?.traversedDistance ?? { base: 0, size: 0 },
      },
    };
  }

  private component(
    allowedDirections: string[],
    constraint: MovementOperation['distance']['horizontal'] | MovementOperation['distance']['vertical'],
    request: { direction: string; distance: DimensionalNumberValue } | undefined,
    context: {
      currentMovementStep: DimensionalNumberValue;
      characteristicValues: ReadonlyMap<string, DimensionalNumberValue>;
    },
  ): { direction: string | null; requestedDistance: DimensionalNumber } {
    if (!request) return { direction: null, requestedDistance: new DimensionalNumber({ base: 0, size: 0 }) };
    if (!allowedDirections.some((direction) => direction === request.direction))
      throw new Error('Направление движения недоступно');
    const distance = new DimensionalNumber(request.distance);
    if (distance.compare(new DimensionalNumber({ base: 0, size: 0 })) <= 0)
      throw new Error('Дистанция движения должна быть положительной');
    if (constraint) {
      const min = new DimensionalNumber(this.expressionService.evaluate(constraint.min, context));
      const max = new DimensionalNumber(this.expressionService.evaluate(constraint.max, context));
      if (distance.compare(min) < (constraint.minInclusive ? 0 : 1)) throw new Error('Дистанция меньше минимальной');
      if (distance.compare(max) > (constraint.maxInclusive ? 0 : -1)) throw new Error('Дистанция больше максимальной');
    }

    return { direction: request.direction, requestedDistance: distance };
  }

  private mergeDirection(current: string | null, next: string | null): string | null {
    if (next === null) return current;
    if (current === null || current === next || current === 'mixed') return current === 'mixed' ? current : next;

    return 'mixed';
  }
}
