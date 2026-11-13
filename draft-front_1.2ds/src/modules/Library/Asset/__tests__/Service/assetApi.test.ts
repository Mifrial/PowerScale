import { describe, it, expect, vi, afterEach } from 'vitest';
import { Engine } from '@/modules/Core/Engine/Service/Engine';
import { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';
import { AssetApi } from '@/modules/Library/Asset/Service/AssetApi';
import { AssetApiError } from '@/modules/Library/Asset/Dto/AssetApiError';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';

const fetchMock = vi.fn();

const item: AssetItem = {
  id: 7,
  collectionModule: 'Roleplay/Scene',
  collectionCode: 'decor',
  collectionTitle: 'Декор',
  filePublicId: 'a'.repeat(32),
  name: 'Дерево',
  width: 256,
  height: 340,
  mime: 'image/png',
  size: 48_000,
  isShared: false,
  ownerId: 1,
  isArchived: false,
  createdAt: 1_700_000_000,
};

function api(): AssetApi {
  const http = new HttpClient({ baseUrl: '/api', getCsrfToken: () => 'csrf-1' });

  return new AssetApi(new Engine(http));
}

function mockResponse(body: unknown, status = 200): void {
  fetchMock.mockResolvedValue(new Response(JSON.stringify(body), { status }));
}

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

describe('AssetApi', () => {
  it('findPage шлёт плоский запрос и разбирает страницу', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse({ success: true, data: { items: [item], total: 1 } });

    const page = await api().findPage({
      collectionModule: 'Roleplay/Scene',
      collectionCode: 'decor',
      scope: 'personal',
      q: 'дерево',
      limit: 24,
      offset: 0,
    });

    expect(page.total).toBe(1);
    expect(page.items[0]?.filePublicId).toBe(item.filePublicId);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe('/api/run?action=asset.findPage');
    expect(JSON.parse(init.body)).toEqual({
      collectionModule: 'Roleplay/Scene',
      collectionCode: 'decor',
      scope: 'personal',
      q: 'дерево',
      limit: 24,
      offset: 0,
    });
  });

  it('create и update возвращают запись', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse({ success: true, data: item });

    const created = await api().create({
      collectionModule: 'Roleplay/Scene',
      collectionCode: 'decor',
      filePublicId: item.filePublicId ?? '',
      scope: 'personal',
      name: 'Дерево',
    });
    expect(created).toEqual(item);
    const [createUrl, createInit] = fetchMock.mock.calls[0];
    expect(createUrl).toBe('/api/run?action=asset.create');
    expect(JSON.parse(createInit.body)).toEqual({
      collectionModule: 'Roleplay/Scene',
      collectionCode: 'decor',
      filePublicId: item.filePublicId,
      scope: 'personal',
      name: 'Дерево',
    });

    mockResponse({ success: true, data: item });
    const updated = await api().update(7, 'Большое дерево');
    expect(updated).toEqual(item);
    const [updateUrl, updateInit] = fetchMock.mock.calls[1];
    expect(updateUrl).toBe('/api/run?action=asset.update');
    expect(JSON.parse(updateInit.body)).toEqual({ id: 7, name: 'Большое дерево' });
  });

  it('archive принимает пустой успех', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse({ success: true, data: [] });

    await expect(api().archive(7)).resolves.toBeUndefined();
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe('/api/run?action=asset.archive');
    expect(JSON.parse(init.body)).toEqual({ id: 7 });
  });

  it('ошибка конверта становится AssetApiError с details', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse(
      {
        success: false,
        data: null,
        error: { code: 'ASSET_DENIED', message: 'denied', details: { reason: 'shared' } },
      },
      400,
    );

    await expect(
      api().create({
        collectionModule: 'Roleplay/Scene',
        collectionCode: 'decor',
        filePublicId: item.filePublicId ?? '',
        scope: 'shared',
      }),
    ).rejects.toMatchObject({
      name: 'AssetApiError',
      code: 'ASSET_DENIED',
      details: { reason: 'shared' },
    });
  });

  it('ошибка без error отдаёт INTERNAL', async () => {
    vi.stubGlobal('fetch', fetchMock);
    mockResponse({ success: false, data: null }, 500);

    await expect(api().archive(7)).rejects.toBeInstanceOf(AssetApiError);
  });
});
