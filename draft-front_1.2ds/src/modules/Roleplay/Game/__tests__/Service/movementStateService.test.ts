import { describe, expect, it } from 'vitest';
import { MovementStateService } from '@/modules/Roleplay/Game/Service/MovementStateService';

const service = new MovementStateService();

describe('MovementStateService', () => {
  it('calculates independent horizontal and vertical speeds', () => {
    const speed = service.fromResolution(
      {
        status: 'completed',
        spentActionPoints: 2,
        operationResults: [{ type: 'movement', status: 'completed' }],
        movement: {
          horizontal: {
            direction: 'front',
            requestedDistance: { base: 3, size: 0 },
            traversedDistance: { base: 3, size: 0 },
          },
          vertical: {
            direction: 'up',
            requestedDistance: { base: 1, size: 0 },
            traversedDistance: { base: 1, size: 0 },
          },
        },
      },
      { base: 1, size: 0 },
    );

    expect(speed).toEqual({
      horizontal: { stepsPerActionPoint: 1, direction: 'front' },
      vertical: { stepsPerActionPoint: 0, direction: 'up' },
    });
  });

  it('keeps a positive direction when movement is less than one step per action point', () => {
    expect(
      service.fromResolution(
        {
          status: 'completed',
          spentActionPoints: 1,
          operationResults: [{ type: 'movement', status: 'completed' }],
          movement: {
            horizontal: {
              direction: 'front',
              requestedDistance: { base: 1, size: -1 },
              traversedDistance: { base: 1, size: -1 },
            },
            vertical: {
              direction: null,
              requestedDistance: { base: 0, size: 0 },
              traversedDistance: { base: 0, size: 0 },
            },
          },
        },
        { base: 1, size: 0 },
      ),
    ).toEqual({
      horizontal: { stepsPerActionPoint: 0, direction: 'front' },
      vertical: { stepsPerActionPoint: 0, direction: null },
    });
  });
});
