import { describe, expect, it, vi } from 'vitest';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import { GameRealtimeApi } from '@/modules/Roleplay/Game/Service/GameRealtimeApi';

describe('GameRealtimeApi', () => {
  it('maps missing future game.sync action to explicit unsupported state', async () => {
    const runAction = vi.fn().mockResolvedValue({
      success: false,
      data: null,
      error: { code: 'UNKNOWN_ACTION', message: 'Unknown action' },
    });
    const api = new GameRealtimeApi({ runAction } as unknown as Engine);

    await expect(api.sync(1, { lastCursor: null })).rejects.toMatchObject({
      name: 'GameRealtimeApiError',
      code: 'GAME_REALTIME_UNAVAILABLE',
    });
  });

  it('reports unavailable subscription transport through the error callback', () => {
    const api = new GameRealtimeApi({ runAction: vi.fn() } as unknown as Engine);
    const onError = vi.fn();

    api.subscribe(1, () => undefined, onError);

    expect(onError).toHaveBeenCalledWith(
      expect.objectContaining({
        name: 'GameRealtimeApiError',
        code: 'GAME_REALTIME_UNAVAILABLE',
      }),
    );
  });
});
