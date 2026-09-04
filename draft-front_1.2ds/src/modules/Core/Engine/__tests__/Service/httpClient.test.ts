import { describe, it, expect, vi, afterEach } from 'vitest';
import { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';
import type { HttpClientConfig } from '@/modules/Core/Engine/Dto/HttpClientConfig';

const fetchMock = vi.fn();

function createClient(config: Partial<HttpClientConfig> = {}): HttpClient {
  return new HttpClient({ baseUrl: '/api', ...config });
}

function mockResponse(body: string, status = 200): void {
  fetchMock.mockResolvedValue(new Response(body, { status }));
}

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

describe('HttpClient.post', () => {
  it('отправляет POST с JSON-заголовками и телом', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse('{"ok":true}');

    await createClient().post('/run', { value: 1 });

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe('/api/run');
    expect(init).toMatchObject({
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: '{"value":1}',
    });
  });

  it('добавляет X-CSRF-Token из конфига', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse('{"ok":true}');

    await createClient({ getCsrfToken: () => 'token-123' }).post('/run');

    const [, init] = fetchMock.mock.calls[0];
    expect(init.headers['X-CSRF-Token']).toBe('token-123');
  });

  it('возвращает разобранный JSON-ответ', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse('{"data":[1,2,3]}', 200);

    const res = await createClient().post<{ data: number[] }>('/run');

    expect(res.ok).toBe(true);
    expect(res.status).toBe(200);
    expect(res.data).toEqual({ data: [1, 2, 3] });
  });

  it('на 400 AUTH_REQUIRED вызывает onUnauthorized', async () => {
    vi.stubGlobal('fetch', fetchMock);
    const onUnauthorized = vi.fn();
    mockResponse(
      '{"success":false,"data":null,"error":{"code":"AUTH_REQUIRED","message":"Authentication is required"}}',
      400,
    );

    const res = await createClient({ onUnauthorized }).post('/run');

    expect(onUnauthorized).toHaveBeenCalledTimes(1);
    expect(res.ok).toBe(false);
    expect(res.status).toBe(400);
  });

  it('на 400 AUTH_DENIED не вылогинивает', async () => {
    vi.stubGlobal('fetch', fetchMock);
    const onUnauthorized = vi.fn();
    mockResponse('{"success":false,"data":null,"error":{"code":"AUTH_DENIED","message":"Permission denied"}}', 400);

    await createClient({ onUnauthorized }).post('/run');

    expect(onUnauthorized).not.toHaveBeenCalled();
  });

  it('на 403 CSRF не вылогинивает', async () => {
    vi.stubGlobal('fetch', fetchMock);
    const onUnauthorized = vi.fn();
    mockResponse('{"success":false,"data":null,"error":{"code":"CSRF","message":"CSRF failed"}}', 403);

    await createClient({ onUnauthorized }).post('/run');

    expect(onUnauthorized).not.toHaveBeenCalled();
  });

  it('на 401 без AUTH_REQUIRED не вылогинивает', async () => {
    vi.stubGlobal('fetch', fetchMock);
    const onUnauthorized = vi.fn();
    mockResponse('{"success":false}', 401);

    await createClient({ onUnauthorized }).post('/run');

    expect(onUnauthorized).not.toHaveBeenCalled();
  });

  it('возвращает ok:false и JSON-тело ошибки на не-2xx', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse('{"success":false}', 400);

    const res = await createClient().post('/run');

    expect(res.ok).toBe(false);
    expect(res.status).toBe(400);
    expect(res.data).toEqual({ success: false });
  });

  it('бросает понятную ошибку на не-JSON теле', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse('<html>Bad Gateway</html>', 502);

    await expect(createClient().post('/run')).rejects.toThrow(/Не удалось разобрать JSON-ответ \(502/);
  });

  it('бросает понятную ошибку на пустом теле', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse('', 200);

    await expect(createClient().post('/run')).rejects.toThrow(/пустое тело ответа/);
  });
});
