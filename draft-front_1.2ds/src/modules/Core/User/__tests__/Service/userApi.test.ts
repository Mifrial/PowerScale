import { describe, expect, it, vi, afterEach } from 'vitest';
import { Engine } from '@/modules/Core/Engine/Service/Engine';
import { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';
import { UserApi } from '@/modules/Core/User/Service/UserApi';
import { UserApiError } from '@/modules/Core/User/Dto/UserApiError';

const fetchMock = vi.fn();
const publicId = 'a'.repeat(32);

function api(): UserApi {
  const http = new HttpClient({ baseUrl: '/api', getCsrfToken: () => 'csrf-1' });

  return new UserApi(new Engine(http));
}

function mockResponse(body: string, status = 200): void {
  fetchMock.mockResolvedValue(new Response(body, { status }));
}

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

describe('UserApi avatar', () => {
  it('setAvatar шлёт плоский кроп и возвращает User', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(JSON.stringify({ success: true, data: { id: 1, name: 'Ivan', login: 'ivan', avatar: publicId } }));

    const user = await api().setAvatar(publicId, { x: 10, y: 20, size: 100 });

    expect(user.avatar).toBe(publicId);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe('/api/run?action=user.setAvatar');
    expect(JSON.parse(init.body)).toEqual({ filePublicId: publicId, cropX: 10, cropY: 20, cropSize: 100 });
  });

  it('setAvatar очищает кроп при снятии', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(JSON.stringify({ success: true, data: { id: 1, name: 'Ivan', login: 'ivan', avatar: null } }));

    await api().setAvatar(null, null);

    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
      filePublicId: null,
      cropX: null,
      cropY: null,
      cropSize: null,
    });
  });

  it('getAvatar разворачивает конверт состояния', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(
      JSON.stringify({
        success: true,
        data: { avatar: publicId, source: { publicId, originalName: 'a.png', width: 10, height: 20 }, crop: null },
      }),
    );

    const state = await api().getAvatar();

    expect(state.source?.publicId).toBe(publicId);
    expect(fetchMock.mock.calls[0][0]).toBe('/api/run?action=user.getAvatar');
  });

  it('отказ setAvatar — UserApiError с кодом и details', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(
      JSON.stringify({
        success: false,
        data: null,
        error: { code: 'USER_INVALID', message: 'bad crop', details: { reason: 'crop' } },
      }),
      400,
    );

    await expect(api().setAvatar(publicId, { x: 0, y: 0, size: 10 })).rejects.toMatchObject({
      name: 'UserApiError',
      code: 'USER_INVALID',
      details: { reason: 'crop' },
    });
  });

  it('пустой ответ getAvatar — UserApiError', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(JSON.stringify({ success: false, data: null }), 500);

    await expect(api().getAvatar()).rejects.toBeInstanceOf(UserApiError);
  });
});
