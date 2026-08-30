import { describe, it, expect, vi, afterEach } from 'vitest';
import { ChatSyncService } from '@/modules/Messages/Chat/Service/ChatSyncService';
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';

const emptySync: SyncResponse = { now: 't1', chats: [], newChats: [], messages: {} };

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
  delete (globalThis as { EventSource?: unknown }).EventSource;
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

function installFakeEventSource() {
  const urls: string[] = [];
  const sources: {
    onerror: (() => void) | null;
    listener: ((e: { data: string }) => void) | null;
  }[] = [];
  class FakeEventSource {
    onerror: (() => void) | null = null;
    listener: ((e: { data: string }) => void) | null = null;
    constructor(url: string) {
      urls.push(url);
      sources.push(this);
    }
    addEventListener(ev: string, cb: (e: { data: string }) => void) {
      if (ev === 'sync') this.listener = cb;
    }
    close() {}
  }
  (globalThis as { EventSource?: unknown }).EventSource = FakeEventSource;

  return { urls, sources };
}

describe('ChatSyncService', () => {
  it('default mode = poll: вызывает getSyncApi().sync и onSync', async () => {
    vi.useFakeTimers();
    const sync = vi.fn().mockResolvedValue(emptySync);
    const { service, onSync, onStatus } = pollService(sync);

    service.connect('');
    await vi.advanceTimersByTimeAsync(0);

    expect(sync).toHaveBeenCalledTimes(1);
    expect(onSync).toHaveBeenCalledWith(expect.objectContaining({ now: 't1' }));
    expect(onStatus).toHaveBeenCalledWith({ status: 'ok', lastError: null });
    service.disconnect();
  });

  it('poll throw → retrying, cursor прежний, второй sync после backoff', async () => {
    vi.useFakeTimers();
    const sync = vi.fn().mockRejectedValue(new Error('сеть'));
    const { service, onSync, onStatus } = pollService(sync);

    service.connect('t0');
    await vi.advanceTimersByTimeAsync(0);

    expect(onStatus).toHaveBeenCalledWith({ status: 'retrying', lastError: 'сеть' });
    expect(onSync).not.toHaveBeenCalled();
    expect(service.lastSyncTimestamp).toBe('t0');
    expect(sync).toHaveBeenCalledTimes(1);

    await vi.advanceTimersByTimeAsync(999);
    expect(sync).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(1);
    expect(sync).toHaveBeenCalledTimes(2);
    service.disconnect();
  });

  it('успех после ошибки → ok, cursor = now, дальше интервал 5s', async () => {
    vi.useFakeTimers();
    const sync = vi.fn().mockRejectedValueOnce(new Error('сеть')).mockResolvedValue(emptySync);
    const { service, onStatus } = pollService(sync);

    service.connect('t0');
    await vi.advanceTimersByTimeAsync(0);
    expect(service.lastSyncTimestamp).toBe('t0');

    await vi.advanceTimersByTimeAsync(1000);
    expect(sync).toHaveBeenCalledTimes(2);
    expect(onStatus).toHaveBeenLastCalledWith({ status: 'ok', lastError: null });
    expect(service.lastSyncTimestamp).toBe('t1');

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

    service.connect('t0');
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

    service.connect('t0');
    await vi.advanceTimersByTimeAsync(0);
    service.disconnect();
    await vi.advanceTimersByTimeAsync(30000);
    expect(sync).toHaveBeenCalledTimes(1);
  });

  it('mode = sse: открывает EventSource на /api/chat/sync и слушает событие sync', () => {
    const { urls, sources } = installFakeEventSource();
    const onSync = vi.fn();
    const onStatus = vi.fn();
    const service = new ChatSyncService({
      mode: 'sse',
      baseUrl: 'http://example.test',
      onSync,
      onStatus,
    });
    service.connect('2026-08-01T00:00:00Z');

    expect(urls[0]).toBe(`http://example.test/api/chat/sync?since=${encodeURIComponent('2026-08-01T00:00:00Z')}`);

    sources[0]?.listener?.({ data: JSON.stringify(emptySync) });
    expect(onSync).toHaveBeenCalledWith(expect.objectContaining({ now: 't1' }));
    expect(onStatus).toHaveBeenCalledWith({ status: 'ok', lastError: null });
    service.disconnect();
  });

  it('sse: мусор и невалидный payload не применяются и не error канала', () => {
    const { sources } = installFakeEventSource();
    const onSync = vi.fn();
    const onStatus = vi.fn();
    const service = new ChatSyncService({ mode: 'sse', baseUrl: 'http://example.test', onSync, onStatus });
    service.connect('t0');

    sources[0]?.listener?.({ data: '{not-json' });
    sources[0]?.listener?.({ data: JSON.stringify({ now: 1, chats: [], newChats: [], messages: {} }) });
    sources[0]?.listener?.({ data: JSON.stringify({ now: 't2', chats: [], newChats: [], messages: [] }) });

    expect(onSync).not.toHaveBeenCalled();
    expect(onStatus).not.toHaveBeenCalled();
    expect(service.lastSyncTimestamp).toBe('t0');
    service.disconnect();
  });

  it('poll через sse-mode не запускается (нет getSyncApi вызова)', () => {
    installFakeEventSource();
    const sync = vi.fn();
    const onSync = vi.fn();
    const service = new ChatSyncService({
      mode: 'sse',
      onSync,
      getSyncApi: () => ({ sync }) as never,
    });
    service.connect('');
    expect(sync).not.toHaveBeenCalled();
    service.disconnect();
  });

  it('sse: onerror не открывает URL сразу; после backoff URL с тем же since', async () => {
    vi.useFakeTimers();
    const { urls, sources } = installFakeEventSource();
    const onStatus = vi.fn();
    const service = new ChatSyncService({
      mode: 'sse',
      baseUrl: 'http://example.test',
      onSync: vi.fn(),
      onStatus,
      initialBackoffMs: 1000,
    });
    service.connect('t0');
    sources[0]?.listener?.({ data: JSON.stringify({ now: 't1', chats: [], newChats: [], messages: {} }) });
    sources[0]?.onerror?.();

    expect(urls).toHaveLength(1);
    expect(onStatus).toHaveBeenLastCalledWith({ status: 'retrying', lastError: 'Соединение чата прервано' });

    await vi.advanceTimersByTimeAsync(999);
    expect(urls).toHaveLength(1);
    await vi.advanceTimersByTimeAsync(1);
    expect(urls).toHaveLength(2);
    expect(urls[1]).toBe(`http://example.test/api/chat/sync?since=${encodeURIComponent('t1')}`);
    service.disconnect();
  });
});
