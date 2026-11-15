import type { IAssetApi } from '@/modules/Library/Asset/Interface/IAssetApi';
import type { AssetCreateRequest } from '@/modules/Library/Asset/Dto/AssetCreateRequest';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import type { AssetPage } from '@/modules/Library/Asset/Dto/AssetPage';
import type { AssetQuery } from '@/modules/Library/Asset/Dto/AssetQuery';
import type { User } from '@/modules/Core/User/Dto/User';
import { AssetApiError } from '@/modules/Library/Asset/Dto/AssetApiError';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { users } from '@/modules/Core/User/Mock/mockUsers';
import { abortableDelay } from '@/modules/Core/Engine/Mock/abortableDelay';

const MODULE = 'Roleplay/Scene';

const initialCatalog: AssetItem[] = [
  {
    id: 1,
    collectionModule: MODULE,
    collectionCode: 'decor',
    collectionTitle: 'Декор',
    filePublicId: 'a'.repeat(32),
    originalName: 'tree.png',
    name: 'Дерево',
    width: 256,
    height: 340,
    mime: 'image/png',
    size: 48_000,
    isShared: true,
    ownerId: null,
    isArchived: false,
    createdAt: 1_700_000_100,
  },
  {
    id: 2,
    collectionModule: MODULE,
    collectionCode: 'decor',
    collectionTitle: 'Декор',
    filePublicId: 'b'.repeat(32),
    originalName: 'bush.png',
    name: 'Куст',
    width: 200,
    height: 160,
    mime: 'image/png',
    size: 26_000,
    isShared: true,
    ownerId: null,
    isArchived: false,
    createdAt: 1_700_000_200,
  },
  {
    id: 3,
    collectionModule: MODULE,
    collectionCode: 'decor',
    collectionTitle: 'Декор',
    filePublicId: 'c'.repeat(32),
    originalName: 'rock.png',
    name: 'Камень',
    width: 200,
    height: 140,
    mime: 'image/png',
    size: 21_000,
    isShared: true,
    ownerId: null,
    isArchived: false,
    createdAt: 1_700_000_300,
  },
  {
    id: 4,
    collectionModule: MODULE,
    collectionCode: 'terrain',
    collectionTitle: 'Текстуры террейна',
    filePublicId: 'd'.repeat(32),
    name: 'Трава',
    width: 512,
    height: 512,
    mime: 'image/jpeg',
    size: 120_000,
    isShared: true,
    ownerId: null,
    isArchived: false,
    createdAt: 1_700_000_400,
  },
  {
    id: 5,
    collectionModule: MODULE,
    collectionCode: 'decor',
    collectionTitle: 'Декор',
    filePublicId: 'e'.repeat(32),
    originalName: 'rock.png',
    name: 'Мой валун',
    width: 180,
    height: 120,
    mime: 'image/png',
    size: 18_000,
    isShared: false,
    ownerId: 1,
    isArchived: false,
    createdAt: 1_700_000_500,
  },
  {
    id: 6,
    collectionModule: MODULE,
    collectionCode: 'decor',
    collectionTitle: 'Декор',
    filePublicId: 'f'.repeat(32),
    name: 'Чужой куст',
    width: 160,
    height: 100,
    mime: 'image/png',
    size: 15_000,
    isShared: false,
    ownerId: 2,
    isArchived: false,
    createdAt: 1_700_000_600,
  },
];

let catalog: AssetItem[] = structuredClone(initialCatalog);
let nextId = 100;

/** Сбрасывает мок библиотеки к стартовому набору. */
export function resetMockAssetApi(): void {
  catalog = structuredClone(initialCatalog);
  nextId = 100;
}

function getCurrentMockUser(): User | null {
  return users.find((user) => user.id === getCurrentUserId()) ?? null;
}

function canEdit(item: AssetItem): boolean {
  return item.isShared ? getCurrentMockUser()?.bypass === true : item.ownerId === getCurrentUserId();
}

function matches(item: AssetItem, query: AssetQuery): boolean {
  if (item.collectionModule !== query.collectionModule) return false;
  if (item.collectionCode !== query.collectionCode) return false;
  if (item.isShared !== (query.scope === 'shared')) return false;
  if (item.isArchived) return false;
  if (query.scope === 'personal' && item.ownerId !== getCurrentUserId()) return false;
  if (query.hasMeta === true && (item.meta === undefined || item.meta === null)) return false;

  const search = query.q?.trim().toLocaleLowerCase('ru') ?? '';

  return search === '' || item.name.toLocaleLowerCase('ru').includes(search);
}

export const mockAssetApi: IAssetApi = {
  async findPage(query: AssetQuery, signal?: AbortSignal): Promise<AssetPage> {
    await abortableDelay(80, signal);
    const matched = catalog
      .filter((item) => matches(item, query))
      .sort((left, right) => left.name.localeCompare(right.name, 'ru'));

    return { items: matched.slice(query.offset, query.offset + query.limit), total: matched.length };
  },

  async create(request: AssetCreateRequest, signal?: AbortSignal): Promise<AssetItem> {
    await abortableDelay(80, signal);
    if (request.scope === 'shared' && getCurrentMockUser()?.bypass !== true) {
      throw new AssetApiError('ASSET_DENIED', 'Only a bypass actor can add shared assets');
    }

    const id = nextId++;
    const item: AssetItem = {
      id,
      collectionModule: request.collectionModule,
      collectionCode: request.collectionCode,
      collectionTitle: request.collectionCode === 'terrain' ? 'Текстуры террейна' : 'Декор',
      filePublicId: request.filePublicId,
      name: request.name?.trim() || `Ассет ${id}`,
      meta: request.meta ?? null,
      width: null,
      height: null,
      mime: 'image/png',
      size: null,
      isShared: request.scope === 'shared',
      ownerId: request.scope === 'shared' ? null : getCurrentUserId(),
      isArchived: false,
      createdAt: Math.floor(Date.now() / 1000),
    };
    catalog = [item, ...catalog];

    return item;
  },

  async update(id: number, name: string, meta?: Record<string, unknown>, signal?: AbortSignal): Promise<AssetItem> {
    await abortableDelay(80, signal);
    const item = catalog.find((entry) => entry.id === id);
    if (!item) throw new AssetApiError('ASSET_NOT_FOUND', 'Asset was not found');
    if (!canEdit(item)) throw new AssetApiError('ASSET_DENIED', 'Asset access is denied');

    const updated = meta === undefined ? { ...item, name } : { ...item, name, meta };
    catalog = catalog.map((entry) => (entry.id === id ? updated : entry));

    return updated;
  },

  async archive(id: number, signal?: AbortSignal): Promise<void> {
    await abortableDelay(80, signal);
    const item = catalog.find((entry) => entry.id === id);
    if (!item) throw new AssetApiError('ASSET_NOT_FOUND', 'Asset was not found');
    if (!canEdit(item)) throw new AssetApiError('ASSET_DENIED', 'Asset access is denied');

    catalog = catalog.map((entry) => (entry.id === id ? { ...entry, isArchived: true } : entry));
  },
};
