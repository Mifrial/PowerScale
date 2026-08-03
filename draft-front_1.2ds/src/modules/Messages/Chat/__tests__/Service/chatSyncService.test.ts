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
});
