import { afterEach, describe, expect, it, vi } from 'vitest';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import { GameRealtimeApi } from '@/modules/Roleplay/Game/Service/GameRealtimeApi';

type SseHandlers = {
  onEvent: (name: string, payload: unknown) => void;
  onError: (error: { code: string; message: string }) => void;
  onEnd?: () => void;
};

function handlersOf(openSse: ReturnType<typeof vi.fn>, call = 0): SseHandlers {
  return openSse.mock.calls[call][2] as SseHandlers;
}

describe('GameRealtimeApi', () => {
  afterEach(() => {
    vi.useRealTimers();
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
    const handlers = handlersOf(openSse);
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

  it('does not reuse the cursor of another game on subscribe', () => {
    const openSse = vi.fn().mockReturnValue({ close: vi.fn() });
    const api = new GameRealtimeApi({ runAction: vi.fn(), openSse } as unknown as Engine);

    api.subscribe(7, vi.fn());
    handlersOf(openSse).onEvent('sync', { cursor: 4, events: [] });

    api.subscribe(8, vi.fn());

    expect(openSse).toHaveBeenLastCalledWith('/game/sync', { gameId: 8 }, expect.anything());
  });

  it('reconnects with the last cursor after the server ends the stream', () => {
    vi.useFakeTimers();
    const openSse = vi.fn().mockReturnValue({ close: vi.fn() });
    const api = new GameRealtimeApi({ runAction: vi.fn(), openSse } as unknown as Engine);

    api.subscribe(7, vi.fn());
    handlersOf(openSse).onEvent('sync', { cursor: 15, events: [] });
    handlersOf(openSse).onEnd?.();

    vi.advanceTimersByTime(2000);

    expect(openSse).toHaveBeenCalledTimes(2);
    expect(openSse).toHaveBeenLastCalledWith('/game/sync', { gameId: 7, lastCursor: 15 }, expect.anything());
  });

  it('reconnects after a channel error', () => {
    vi.useFakeTimers();
    const openSse = vi.fn().mockReturnValue({ close: vi.fn() });
    const api = new GameRealtimeApi({ runAction: vi.fn(), openSse } as unknown as Engine);
    const onError = vi.fn();

    api.subscribe(7, vi.fn(), onError);
    handlersOf(openSse).onError({ code: 'INTERNAL', message: 'поток оборван' });

    expect(onError).toHaveBeenCalledWith(expect.objectContaining({ code: 'INTERNAL' }));
    vi.advanceTimersByTime(2000);

    expect(openSse).toHaveBeenCalledTimes(2);
  });

  it('does not reconnect after an explicit close', () => {
    vi.useFakeTimers();
    const openSse = vi.fn().mockReturnValue({ close: vi.fn() });
    const api = new GameRealtimeApi({ runAction: vi.fn(), openSse } as unknown as Engine);

    const stop = api.subscribe(7, vi.fn());
    stop();
    handlersOf(openSse).onEnd?.();
    vi.advanceTimersByTime(10000);

    expect(openSse).toHaveBeenCalledTimes(1);
  });

  it('ignores frames of a stale stream after resubscribe', () => {
    const openSse = vi.fn().mockReturnValue({ close: vi.fn() });
    const api = new GameRealtimeApi({ runAction: vi.fn(), openSse } as unknown as Engine);
    const stale = vi.fn();
    const fresh = vi.fn();

    api.subscribe(7, stale);
    api.subscribe(7, fresh);

    handlersOf(openSse, 0).onEvent('sync', {
      cursor: 3,
      events: [{ eventId: 'old', cursor: 3, source: 'strike', sourceId: 1, keys: [] }],
    });
    handlersOf(openSse, 1).onEvent('sync', {
      cursor: 4,
      events: [{ eventId: 'new', cursor: 4, source: 'strike', sourceId: 2, keys: [] }],
    });

    expect(stale).not.toHaveBeenCalled();
    expect(fresh).toHaveBeenCalledWith(expect.objectContaining({ eventId: 'new' }));
  });
});
