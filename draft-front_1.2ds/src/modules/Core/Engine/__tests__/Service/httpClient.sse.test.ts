import { describe, it, expect, vi, afterEach } from 'vitest';
import { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';

const fetchMock = vi.fn();

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function streamBody(chunks: string[]): ReadableStream<Uint8Array> {
  const encoder = new TextEncoder();

  return new ReadableStream({
    start(controller) {
      for (const chunk of chunks) {
        controller.enqueue(encoder.encode(chunk));
      }
      controller.close();
    },
  });
}

describe('HttpClient.openSse', () => {
  it('GET без CSRF, сериализует 0, не шлёт undefined', async () => {
    vi.stubGlobal('fetch', fetchMock);
    fetchMock.mockResolvedValue(new Response(streamBody([]), { status: 200 }));
    const onEvent = vi.fn();
    const onError = vi.fn();
    const handle = new HttpClient({ baseUrl: '/api', getCsrfToken: () => 'tok' }).openSse(
      '/chat/sync',
      { since: 0, afterId: undefined },
      { onEvent, onError },
    );

    await vi.waitFor(() => {
      expect(fetchMock).toHaveBeenCalled();
    });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe('/api/chat/sync?since=0');
    expect(init.method).toBe('GET');
    expect(init.credentials).toBe('include');
    expect(init.headers['Accept']).toBe('text/event-stream');
    expect(init.headers['X-CSRF-Token']).toBeUndefined();
    handle.close();
  });

  it('event: sync и : ping; мусорный JSON не onError', async () => {
    vi.stubGlobal('fetch', fetchMock);
    fetchMock.mockResolvedValue(
      new Response(
        streamBody([': ping\n\n', 'event: sync\ndata: {"now":1,"afterId":0}\n\n', 'event: sync\ndata: {bad\n\n']),
        { status: 200 },
      ),
    );
    const onEvent = vi.fn();
    const onError = vi.fn();
    new HttpClient({ baseUrl: '/api' }).openSse('/chat/sync', {}, { onEvent, onError });

    await vi.waitFor(() => {
      expect(onEvent).toHaveBeenCalledWith('sync', { now: 1, afterId: 0 });
    });
    expect(onEvent).toHaveBeenCalledTimes(1);
  });

  it('400 AUTH_REQUIRED — login, не onError', async () => {
    vi.stubGlobal('fetch', fetchMock);
    const onUnauthorized = vi.fn();
    fetchMock.mockResolvedValue(
      new Response(
        '{"success":false,"data":null,"error":{"code":"AUTH_REQUIRED","message":"Authentication is required"}}',
        { status: 400 },
      ),
    );
    const onError = vi.fn();
    new HttpClient({ baseUrl: '/api', onUnauthorized }).openSse('/chat/sync', {}, { onEvent: vi.fn(), onError });

    await vi.waitFor(() => {
      expect(onUnauthorized).toHaveBeenCalledTimes(1);
    });
    expect(onError).not.toHaveBeenCalled();
  });

  it('400 CHAT_INVALID — onError с кодом', async () => {
    vi.stubGlobal('fetch', fetchMock);
    fetchMock.mockResolvedValue(
      new Response('{"success":false,"data":null,"error":{"code":"CHAT_INVALID","message":"bad"}}', { status: 400 }),
    );
    const onError = vi.fn();
    new HttpClient({ baseUrl: '/api' }).openSse('/chat/sync', {}, { onEvent: vi.fn(), onError });

    await vi.waitFor(() => {
      expect(onError).toHaveBeenCalledWith({ code: 'CHAT_INVALID', message: 'bad' });
    });
  });

  it('close() не вызывает onError', async () => {
    vi.stubGlobal('fetch', fetchMock);
    fetchMock.mockImplementation((_url, init: { signal: AbortSignal }) => {
      return new Promise((_, reject) => {
        init.signal.addEventListener('abort', () => {
          reject(new DOMException('Aborted', 'AbortError'));
        });
      });
    });
    const onError = vi.fn();
    const handle = new HttpClient({ baseUrl: '/api' }).openSse('/chat/sync', {}, { onEvent: vi.fn(), onError });
    handle.close();
    await Promise.resolve();
    await Promise.resolve();
    expect(onError).not.toHaveBeenCalled();
  });
});
