import { beforeEach, describe, expect, it } from 'vitest';
import { mockAssetApi, resetMockAssetApi } from '@/modules/Library/Asset/Mock/mockAssetApi';

const sharedQuery = {
  collectionModule: 'Roleplay/Scene',
  collectionCode: 'decor',
  scope: 'shared' as const,
  limit: 24,
  offset: 0,
};

const personalQuery = { ...sharedQuery, scope: 'personal' as const };

beforeEach(() => {
  resetMockAssetApi();
});

describe('mockAssetApi', () => {
  it('делит общую и личную части и не показывает чужое', async () => {
    const shared = await mockAssetApi.findPage(sharedQuery);
    expect(shared.total).toBe(3);
    expect(shared.items.map((item) => item.name)).toEqual(['Дерево', 'Камень', 'Куст']);

    const personal = await mockAssetApi.findPage(personalQuery);
    expect(personal.total).toBe(1);
    expect(personal.items[0]?.name).toBe('Мой валун');

    const terrain = await mockAssetApi.findPage({ ...sharedQuery, collectionCode: 'terrain' });
    expect(terrain.items.map((item) => item.name)).toEqual(['Трава']);
  });

  it('ищет по подстроке имени', async () => {
    const page = await mockAssetApi.findPage({ ...sharedQuery, q: 'ку' });
    expect(page.items.map((item) => item.name)).toEqual(['Куст']);
  });

  it('личное создание доступно, общее — только bypass', async () => {
    const created = await mockAssetApi.create({
      collectionModule: 'Roleplay/Scene',
      collectionCode: 'decor',
      filePublicId: '1'.repeat(32),
      scope: 'personal',
      name: 'Новый куст',
    });
    expect(created.ownerId).toBe(1);
    expect(created.isShared).toBe(false);

    await expect(
      mockAssetApi.create({
        collectionModule: 'Roleplay/Scene',
        collectionCode: 'decor',
        filePublicId: '2'.repeat(32),
        scope: 'shared',
      }),
    ).rejects.toMatchObject({ code: 'ASSET_DENIED' });
  });

  it('правит и архивирует только своё', async () => {
    const updated = await mockAssetApi.update(5, 'Мой камень');
    expect(updated.name).toBe('Мой камень');

    await expect(mockAssetApi.update(6, 'Чужой')).rejects.toMatchObject({ code: 'ASSET_DENIED' });
    await expect(mockAssetApi.update(1, 'Общий')).rejects.toMatchObject({ code: 'ASSET_DENIED' });

    await mockAssetApi.archive(5);
    const personal = await mockAssetApi.findPage(personalQuery);
    expect(personal.total).toBe(0);
  });
});
