import { describe, expect, it } from 'vitest';
import { getCurrentSpeed, setCurrentSpeed } from '@/modules/Roleplay/Game/Mock/mockGameMovementState';

describe('mock game movement state', () => {
  it('stores current speed per game participant', async () => {
    const speed = {
      horizontal: { stepsPerActionPoint: 2, direction: 'front' as const },
      vertical: { stepsPerActionPoint: 0, direction: null },
    };
    await setCurrentSpeed(77, 'character:1', speed);

    expect(await getCurrentSpeed(77, 'character:1')).toEqual(speed);
    expect(await getCurrentSpeed(77, 'character:2')).toEqual({
      horizontal: { stepsPerActionPoint: 0, direction: null },
      vertical: { stepsPerActionPoint: 0, direction: null },
    });
  });
});
