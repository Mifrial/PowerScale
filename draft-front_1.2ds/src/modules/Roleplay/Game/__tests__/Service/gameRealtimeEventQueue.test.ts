import { describe, expect, it, vi } from 'vitest';
import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';
import { GameRealtimeEventQueue } from '@/modules/Roleplay/Game/Service/GameRealtimeEventQueue';

function event(cursor: number): GameRealtimeEvent {
  return {
    gameId: 4,
    eventId: `4.${cursor}`,
    cursor,
    actualVersion: cursor,
    changedSections: [],
    sessionId: null,
    battleId: null,
    source: 'economy',
    sourceId: cursor,
    keys: [],
  };
}

describe('GameRealtimeEventQueue', () => {
  it('applies frames strictly in order', async () => {
    const applied: number[] = [];
    const queue = new GameRealtimeEventQueue(
      async (frame) => {
        applied.push(frame.cursor);

        return true;
      },
      () => undefined,
    );

    queue.enqueue(event(1));
    queue.enqueue(event(2));
    queue.enqueue(event(3));
    await queue.idle();

    expect(applied).toEqual([1, 2, 3]);
  });

  it('does not block later frames when one frame misses', async () => {
    const applied: number[] = [];
    const missed: number[] = [];
    const queue = new GameRealtimeEventQueue(
      async (frame) => {
        applied.push(frame.cursor);

        return frame.cursor !== 2;
      },
      (frame) => missed.push(frame.cursor),
    );

    queue.enqueue(event(1));
    queue.enqueue(event(2));
    queue.enqueue(event(3));
    await queue.idle();

    expect(applied).toEqual([1, 2, 3]);
    expect(missed).toEqual([2]);
  });

  it('treats a thrown apply as a missed frame', async () => {
    const onMissed = vi.fn();
    const queue = new GameRealtimeEventQueue(async () => {
      throw new Error('network');
    }, onMissed);

    queue.enqueue(event(1));
    queue.enqueue(event(2));
    await queue.idle();

    expect(onMissed).toHaveBeenCalledTimes(2);
    expect(onMissed).toHaveBeenNthCalledWith(2, expect.objectContaining({ cursor: 2 }));
  });
});
