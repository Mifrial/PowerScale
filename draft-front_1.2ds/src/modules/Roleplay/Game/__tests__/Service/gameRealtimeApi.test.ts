import { describe, expect, it, vi } from 'vitest';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import { GameRealtimeApi } from '@/modules/Roleplay/Game/Service/GameRealtimeApi';

describe('GameRealtimeApi', () => {
  it('maps missing future game.sync action to explicit unsupported state', async () => {
    const api = new GameRealtimeApi({ runAction: vi.fn(), openSse: vi.fn() } as unknown as Engine);

    await expect(api.sync(1, { lastCursor: null })).rejects.toMatchObject({
      name: 'GameRealtimeApiError',
      code: 'GAME_REALTIME_UNAVAILABLE',
    });
  });

  it('opens /game/sync and forwards strike frames with battleId', () => {
    const openSse = vi.fn().mockReturnValue({ close: vi.fn() });
    const api = new GameRealtimeApi({ runAction: vi.fn(), openSse } as unknown as Engine);
    const listener = vi.fn();

    api.subscribe(7, listener);

    expect(openSse).toHaveBeenCalledWith(
      '/game/sync',
      { gameId: 7 },
      expect.objectContaining({ onEvent: expect.any(Function) }),
    );
    const handlers = openSse.mock.calls[0][2] as {
      onEvent: (name: string, payload: unknown) => void;
    };
    handlers.onEvent('sync', {
      cursor: 4,
      events: [{ eventId: 'e1', cursor: 4, source: 'strike', sourceId: 9, keys: [], battleId: 12 }],
    });

    expect(listener).toHaveBeenCalledWith(
      expect.objectContaining({
        gameId: 7,
        source: 'strike',
        sourceId: 9,
        battleId: 12,
        cursor: 4,
      }),
    );
  });
});
