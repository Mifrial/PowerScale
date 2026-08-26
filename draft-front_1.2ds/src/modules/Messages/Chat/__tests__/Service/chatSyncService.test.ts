import { describe, it, expect, vi, afterEach } from 'vitest';
import { ChatSyncService } from '@/modules/Messages/Chat/Service/ChatSyncService';
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';

const emptySync: SyncResponse = { now: 't1', chats: [], newChats: [], messages: {} };

afterEach(() => {
  vi.restoreAllMocks();
  delete (globalThis as any).EventSource;
});

describe('ChatSyncService', () => {
  it('default mode = poll: вызывает getSyncApi().sync и onSync', async () => {
    const sync = vi.fn().mockResolvedValue(emptySync);
    const onSync = vi.fn();
    const service = new ChatSyncService({ onSync, getSyncApi: () => ({ sync }) as never });

    service.connect('');
    await Promise.resolve();
    await Promise.resolve();

    expect(sync).toHaveBeenCalled();
    expect(onSync).toHaveBeenCalledWith(expect.objectContaining({ now: 't1' }));
    service.disconnect();
  });

  it('mode = sse: открывает EventSource на /api/chat/sync и слушает событие sync', () => {
    const listeners: Record<string, (e: { data: string }) => void> = {};
    let openedUrl = '';
    class FakeEventSource {
      constructor(url: string) {
        openedUrl = url;
      }
      addEventListener(ev: string, cb: (e: { data: string }) => void) {
        listeners[ev] = cb;
      }
      close() {}
    }
    (globalThis as any).EventSource = FakeEventSource;

    const onSync = vi.fn();
    const service = new ChatSyncService({ mode: 'sse', baseUrl: 'http://example.test', onSync });
    service.connect('2026-08-01T00:00:00Z');

    expect(openedUrl).toBe(`http://example.test/api/chat/sync?since=${encodeURIComponent('2026-08-01T00:00:00Z')}`);

    listeners['sync']({ data: JSON.stringify(emptySync) });
    expect(onSync).toHaveBeenCalledWith(expect.objectContaining({ now: 't1' }));
  });

  it('sse: мусор и невалидный payload не применяются', () => {
    const listeners: Record<string, (e: { data: string }) => void> = {};
    class FakeEventSource {
      constructor(_url: string) {}
      addEventListener(ev: string, cb: (e: { data: string }) => void) {
        listeners[ev] = cb;
      }
      close() {}
    }
    (globalThis as { EventSource?: unknown }).EventSource = FakeEventSource;

    const onSync = vi.fn();
    const service = new ChatSyncService({ mode: 'sse', baseUrl: 'http://example.test', onSync });
    service.connect('t0');

    listeners['sync']({ data: '{not-json' });
    listeners['sync']({ data: JSON.stringify({ now: 1, chats: [], newChats: [], messages: {} }) });
    listeners['sync']({ data: JSON.stringify({ now: 't2', chats: [], newChats: [], messages: [] }) });

    expect(onSync).not.toHaveBeenCalled();
    expect(service.lastSyncTimestamp).toBe('t0');
    service.disconnect();
  });

  it('poll через sse-mode не запускается (нет getSyncApi вызова)', () => {
    class FakeEventSource {
      constructor(_url: string) {}
      addEventListener() {}
      close() {}
    }
    (globalThis as any).EventSource = FakeEventSource;

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

  it('sse: onerror переоткрывает поток с актуальным since', () => {
    const urls: string[] = [];
    const sources: { onerror: (() => void) | null; listener: ((e: { data: string }) => void) | null }[] = [];
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

    const onSync = vi.fn();
    const service = new ChatSyncService({ mode: 'sse', baseUrl: 'http://example.test', onSync });
    service.connect('t0');
    sources[0]?.listener?.({ data: JSON.stringify({ now: 't1', chats: [], newChats: [], messages: {} }) });
    sources[0]?.onerror?.();

    expect(urls).toHaveLength(2);
    expect(urls[1]).toBe(`http://example.test/api/chat/sync?since=${encodeURIComponent('t1')}`);
    service.disconnect();
  });
});
