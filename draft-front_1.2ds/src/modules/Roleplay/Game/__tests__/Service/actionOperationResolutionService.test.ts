import { describe, expect, it } from 'vitest';
import { ActionOperationResolutionService } from '@/modules/Roleplay/Game/Service/ActionOperationResolutionService';
import type { ISpatialResolver } from '@/modules/Roleplay/Game/Interface/ISpatialResolver';

describe('ActionOperationResolutionService', () => {
  it('resolves dimensional bounds for the UI default value', () => {
    const service = new ActionOperationResolutionService();
    const bounds = service.movementBounds(
      {
        type: 'movement',
        allowedDirections: { horizontal: ['front'], vertical: [] },
        distance: {
          horizontal: {
            min: { type: 'steps', count: 0 },
            max: { type: 'current_movement_step', multiplier: 1 },
            minInclusive: false,
            maxInclusive: true,
          },
        },
      },
      'horizontal',
      { currentMovementStep: { base: 1, size: -1 }, characteristicValues: new Map() },
    );

    expect(bounds).toEqual({ min: { base: 0, size: 0 }, max: { base: 1, size: -1 } });
  });

  it('aggregates front and up independently', () => {
    const service = new ActionOperationResolutionService();
    const resolution = service.resolve(
      [
        {
          type: 'movement',
          allowedDirections: { horizontal: ['front'], vertical: ['up'] },
          distance: {
            horizontal: {
              min: { type: 'steps', count: 0 },
              max: { type: 'current_movement_step', multiplier: 2 },
              minInclusive: false,
              maxInclusive: true,
            },
            vertical: {
              min: { type: 'steps', count: 0 },
              max: { type: 'current_movement_step', multiplier: 1 },
              minInclusive: false,
              maxInclusive: true,
            },
          },
        },
      ],
      [
        {
          movement: {
            horizontal: { direction: 'front', distance: { base: 1, size: 0 } },
            vertical: { direction: 'up', distance: { base: 1, size: -1 } },
          },
        },
      ],
      {
        currentMovementStep: { base: 1, size: 0 },
        characteristicValues: new Map(),
        spentActionPoints: 1,
      },
    );

    expect(resolution.movement.horizontal).toEqual({
      direction: 'front',
      requestedDistance: { base: 1, size: 0 },
      traversedDistance: { base: 1, size: 0 },
    });
    expect(resolution.movement.vertical).toEqual({
      direction: 'up',
      requestedDistance: { base: 1, size: -1 },
      traversedDistance: { base: 1, size: -1 },
    });
  });

  it('rejects a zero movement request', () => {
    const service = new ActionOperationResolutionService();

    expect(() =>
      service.resolve(
        [
          {
            type: 'movement',
            allowedDirections: { horizontal: ['front'], vertical: [] },
            distance: {
              horizontal: {
                min: { type: 'steps', count: 0 },
                max: { type: 'current_movement_step', multiplier: 1 },
                minInclusive: false,
                maxInclusive: true,
              },
            },
          },
        ],
        [{ movement: { horizontal: { direction: 'front', distance: { base: 0, size: 0 } } } }],
        { currentMovementStep: { base: 1, size: 0 }, characteristicValues: new Map(), spentActionPoints: 1 },
      ),
    ).toThrow('положительной');
  });

  it('keeps a positive partial distance when a spatial resolver reports collision', () => {
    const resolver: ISpatialResolver = {
      resolveMovement: () => ({
        status: 'collision',
        horizontal: { traversedDistance: { base: 1, size: -1 } },
      }),
    };
    const service = new ActionOperationResolutionService(undefined, resolver);
    const resolution = service.resolve(
      [
        {
          type: 'movement',
          allowedDirections: { horizontal: ['front'], vertical: [] },
          distance: {
            horizontal: {
              min: { type: 'current_movement_step', multiplier: 1 },
              max: { type: 'current_movement_step', multiplier: 2 },
              minInclusive: true,
              maxInclusive: true,
            },
          },
        },
      ],
      [{ movement: { horizontal: { direction: 'front', distance: { base: 2, size: 0 } } } }],
      { currentMovementStep: { base: 1, size: 0 }, characteristicValues: new Map(), spentActionPoints: 1 },
    );

    expect(resolution.status).toBe('interrupted');
    expect(resolution.interruptionReason).toBe('collision');
    expect(resolution.movement.horizontal.traversedDistance).toEqual({ base: 1, size: -1 });
  });
});
