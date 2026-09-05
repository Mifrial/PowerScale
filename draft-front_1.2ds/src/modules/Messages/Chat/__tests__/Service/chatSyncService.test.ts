import { describe, it, expect, vi, afterEach } from 'vitest';
import { ChatSyncService } from '@/modules/Messages/Chat/Service/ChatSyncService';
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { SseHandlers } from '@/modules/Core/Engine/Dto/SseHandlers';

const emptySync: SyncResponse = { now: 100, afterId: 0, chats: [], newChats: [], messages: {} };

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

function pollService(sync: () => Promise<SyncResponse>) {
  const onSync = vi.fn();
  const onStatus = vi.fn();
  const service = new ChatSyncService({
    onSync,
    onStatus,
    getSyncApi: () => ({ sync }) as never,
    pollInterval: 5000,
    initialBackoffMs: 1000,
    maxBackoffMs: 30000,
  });

  return { service, onSync, onStatus };
}

function sseHarness() {
  const opens: {
    path: string;
    query: Record<string, string | number | undefined>;
    handlers: SseHandlers;
    close: ReturnType<typeof vi.fn>;
  }[] = [];
  const engine = {
    openSse: (path: string, query: Record<string, string | number | undefined>, handlers: SseHandlers) => {
      const close = vi.fn();
      opens.push({ path, query, handlers, close });

      return { close };
    },
  } as unknown as Engine;

  return { engine, opens };
}

describe('ChatSyncService', () => {
  it('default mode = poll: вызывает getSyncApi().sync(0) и onSync', async () => {
    vi.useFakeTimers();
    const sync = vi.fn().mockResolvedValue(emptySync);
    const { service, onSync, onStatus } = pollService(sync);

    service.connect(null);
    await vi.advanceTimersByTimeAsync(0);

    expect(sync).toHaveBeenCalledWith(0);
    expect(onSync).toHaveBeenCalledWith(expect.objectContaining({ now: 100 }));
    expect(onStatus).toHaveBeenCalledWith({ status: 'ok', lastError: null });
    service.disconnect();
  });

  it('poll throw → retrying, cursor прежний, второй sync после backoff', async () => {
    vi.useFakeTimers();
    const sync = vi.fn().mockRejectedValue(new Error('сеть'));
    const { service, onSync, onStatus } = pollService(sync);
    const cursor = { since: 10, afterId: 0 };

    service.connect(cursor);
    await vi.advanceTimersByTimeAsync(0);

    expect(onStatus).toHaveBeenCalledWith({ status: 'retrying', lastError: 'сеть' });
    expect(onSync).not.toHaveBeenCalled();
    expect(service.lastSyncCursor).toEqual(cursor);
    expect(sync).toHaveBeenCalledTimes(1);
    expect(sync).toHaveBeenCalledWith(10);

    await vi.advanceTimersByTimeAsync(999);
    expect(sync).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(1);
    expect(sync).toHaveBeenCalledTimes(2);
    service.disconnect();
  });

  it('успех после ошибки → ok, cursor = now/afterId, дальше интервал 5s', async () => {
    vi.useFakeTimers();
    const sync = vi.fn().mockRejectedValueOnce(new Error('сеть')).mockResolvedValue(emptySync);
    const { service, onStatus } = pollService(sync);

    service.connect({ since: 10, afterId: 0 });
    await vi.advanceTimersByTimeAsync(0);
    expect(service.lastSyncCursor).toEqual({ since: 10, afterId: 0 });

    await vi.advanceTimersByTimeAsync(1000);
    expect(sync).toHaveBeenCalledTimes(2);
    expect(onStatus).toHaveBeenLastCalledWith({ status: 'ok', lastError: null });
    expect(service.lastSyncCursor).toEqual({ since: 100, afterId: 0 });

    await vi.advanceTimersByTimeAsync(4999);
    expect(sync).toHaveBeenCalledTimes(2);
    await vi.advanceTimersByTimeAsync(1);
    expect(sync).toHaveBeenCalledTimes(3);
    service.disconnect();
  });

  it('retryNow не ждёт backoff', async () => {
    vi.useFakeTimers();
    const sync = vi.fn().mockRejectedValue(new Error('сеть'));
    const { service } = pollService(sync);

    service.connect({ since: 10, afterId: 0 });
    await vi.advanceTimersByTimeAsync(0);
    expect(sync).toHaveBeenCalledTimes(1);

    service.retryNow();
    await vi.advanceTimersByTimeAsync(0);
    expect(sync).toHaveBeenCalledTimes(2);
    service.disconnect();
  });

  it('disconnect во время backoff — больше никаких sync', async () => {
    vi.useFakeTimers();
    const sync = vi.fn().mockRejectedValue(new Error('сеть'));
    const { service } = pollService(sync);

    service.connect({ since: 10, afterId: 0 });
    await vi.advanceTimersByTimeAsync(0);
    service.disconnect();
    await vi.advanceTimersByTimeAsync(30000);
    expect(sync).toHaveBeenCalledTimes(1);
  });

  it('mode = sse: live без query; кадр с now:0 легален', () => {
    const { engine, opens } = sseHarness();
    const onSync = vi.fn();
    const onStatus = vi.fn();
    const service = new ChatSyncService({
      mode: 'sse',
      engine,
      onSync,
      onStatus,
    });
    service.connect(null);

    expect(opens[0]?.path).toBe('/chat/sync');
    expect(opens[0]?.query).toEqual({});

    opens[0]?.handlers.onEvent('sync', { now: 0, afterId: 0, chats: [], newChats: [], messages: {} });
    expect(onSync).toHaveBeenCalledWith(expect.objectContaining({ now: 0, afterId: 0 }));
    expect(onStatus).toHaveBeenCalledWith({ status: 'ok', lastError: null });
    expect(service.lastSyncCursor).toEqual({ since: 0, afterId: 0 });
    service.disconnect();
  });

  it('sse: afterId=0 не кладёт ключ; afterId>0 кладёт', () => {
    const { engine, opens } = sseHarness();
    const service = new ChatSyncService({ mode: 'sse', engine, onSync: vi.fn() });
    service.connect({ since: 5, afterId: 0 });
    expect(opens[0]?.query).toEqual({ since: 5 });

    service.disconnect();
    service.connect({ since: 5, afterId: 9 });
    expect(opens[1]?.query).toEqual({ since: 5, afterId: 9 });
    service.disconnect();
  });

  it('sse: мусор и невалидный payload не применяются и не error канала', () => {
    const { engine, opens } = sseHarness();
    const onSync = vi.fn();
    const onStatus = vi.fn();
    const service = new ChatSyncService({ mode: 'sse', engine, onSync, onStatus });
    service.connect({ since: 10, afterId: 1 });

    opens[0]?.handlers.onEvent('ping', emptySync);
    opens[0]?.handlers.onEvent('sync', { now: 1, chats: [], newChats: [], messages: [] });
    opens[0]?.handlers.onEvent('sync', { now: 't2', afterId: 0, chats: [], newChats: [], messages: {} });

    expect(onSync).not.toHaveBeenCalled();
    expect(onStatus).not.toHaveBeenCalled();
    expect(service.lastSyncCursor).toEqual({ since: 10, afterId: 1 });
    service.disconnect();
  });

  it('poll через sse-mode не запускается (нет getSyncApi вызова)', () => {
    const { engine } = sseHarness();
    const sync = vi.fn();
    const onSync = vi.fn();
    const service = new ChatSyncService({
      mode: 'sse',
      engine,
      onSync,
      getSyncApi: () => ({ sync }) as never,
    });
    service.connect(null);
    expect(sync).not.toHaveBeenCalled();
    service.disconnect();
  });

  it('sse: onError не открывает сразу; после backoff тот же cursor', async () => {
    vi.useFakeTimers();
    const { engine, opens } = sseHarness();
    const onStatus = vi.fn();
    const service = new ChatSyncService({
      mode: 'sse',
      engine,
      onSync: vi.fn(),
      onStatus,
      initialBackoffMs: 1000,
    });
    service.connect({ since: 10, afterId: 2 });
    opens[0]?.handlers.onEvent('sync', { now: 100, afterId: 3, chats: [], newChats: [], messages: {} });
    opens[0]?.handlers.onError({ code: 'INTERNAL', message: 'Соединение чата прервано' });

    expect(opens).toHaveLength(1);
    expect(onStatus).toHaveBeenLastCalledWith({ status: 'retrying', lastError: 'Соединение чата прервано' });

    await vi.advanceTimersByTimeAsync(999);
    expect(opens).toHaveLength(1);
    await vi.advanceTimersByTimeAsync(1);
    expect(opens).toHaveLength(2);
    expect(opens[1]?.query).toEqual({ since: 100, afterId: 3 });
    service.disconnect();
  });

  it('CHAT_INVALID на курсоре — один live reconnect; повтор на live — баннер', () => {
    const { engine, opens } = sseHarness();
    const onStatus = vi.fn();
    const service = new ChatSyncService({ mode: 'sse', engine, onSync: vi.fn(), onStatus });
    service.connect({ since: 10, afterId: 2 });

    opens[0]?.handlers.onError({ code: 'CHAT_INVALID', message: 'кривой курсор' });
    expect(opens).toHaveLength(2);
    expect(opens[1]?.query).toEqual({});
    expect(onStatus).not.toHaveBeenCalled();

    opens[1]?.handlers.onError({ code: 'CHAT_INVALID', message: 'кривой курсор' });
    expect(opens).toHaveLength(2);
    expect(onStatus).toHaveBeenCalledWith({ status: 'retrying', lastError: 'кривой курсор' });
    service.disconnect();
  });
});
