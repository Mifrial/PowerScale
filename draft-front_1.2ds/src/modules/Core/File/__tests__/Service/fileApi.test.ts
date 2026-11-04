import { describe, it, expect, vi, afterEach } from 'vitest';
import { Engine } from '@/modules/Core/Engine/Service/Engine';
import { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';
import { FileApi } from '@/modules/Core/File/Service/FileApi';
import { FileApiError } from '@/modules/Core/File/Dto/FileApiError';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';

const fetchMock = vi.fn();

const record: FileRecord = {
  id: 7,
  publicId: 'a'.repeat(32),
  ownerId: 1,
  originalName: 'cover.png',
  mime: 'image/png',
  size: 1234,
  width: 10,
  height: 20,
  sha256: 'b'.repeat(64),
  isArchived: false,
  archivedAt: null,
  createdAt: 1_700_000_000,
};

function api(): FileApi {
  const http = new HttpClient({ baseUrl: '/api', getCsrfToken: () => 'csrf-1' });

  return new FileApi(new Engine(http));
}

function mockResponse(body: string, status = 200): void {
  fetchMock.mockResolvedValue(new Response(body, { status }));
}

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

describe('FileApi', () => {
  it('upload шлёт FormData без Content-Type и возвращает запись', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(JSON.stringify({ success: true, data: record }));
    const file = new File(['bytes'], 'cover.png', { type: 'image/png' });

    const uploaded = await api().upload(file);

    expect(uploaded).toEqual(record);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe('/api/file/upload');
    expect(init.method).toBe('POST');
    expect(init.body).toBeInstanceOf(FormData);
    expect((init.body as FormData).get('file')).toBe(file);
    expect(init.headers['Content-Type']).toBeUndefined();
    expect(init.headers['X-CSRF-Token']).toBe('csrf-1');
  });

  it('upload разворачивает конверт в FileApiError с details', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(
      JSON.stringify({
        success: false,
        data: null,
        error: { code: 'FILE_TOO_LARGE', message: 'too large', details: { maxSize: 10 } },
      }),
      400,
    );

    await expect(api().upload(new File(['x'], 'x.png', { type: 'image/png' }))).rejects.toMatchObject({
      name: 'FileApiError',
      code: 'FILE_TOO_LARGE',
      details: { maxSize: 10 },
    });
  });

  it('findPage и get ходят через runAction', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(JSON.stringify({ success: true, data: { items: [record], total: 1 } }));

    const page = await api().findPage({ limit: 10, offset: 0, isArchived: false });

    expect(page.total).toBe(1);
    expect(page.items[0]?.publicId).toBe(record.publicId);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe('/api/run?action=file.findPage');
    expect(JSON.parse(init.body)).toEqual({ limit: 10, offset: 0, isArchived: false });

    mockResponse(JSON.stringify({ success: true, data: record }));
    const got = await api().get(7);
    expect(got).toEqual(record);
  });

  it('getViewUrl строит путь байтов', () => {
    expect(api().getViewUrl(record.publicId)).toBe(`/api/file/${record.publicId}`);
  });

  it('ошибка без data — FileApiError', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(JSON.stringify({ success: false, data: null }), 500);

    await expect(api().findPage({ limit: 10, offset: 0 })).rejects.toBeInstanceOf(FileApiError);
  });
});
