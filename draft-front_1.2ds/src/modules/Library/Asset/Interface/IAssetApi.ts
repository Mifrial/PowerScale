import type { AssetCreateRequest } from '@/modules/Library/Asset/Dto/AssetCreateRequest';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import type { AssetPage } from '@/modules/Library/Asset/Dto/AssetPage';
import type { AssetQuery } from '@/modules/Library/Asset/Dto/AssetQuery';

/** Транспорт библиотеки ассетов. */
export interface IAssetApi {
  findPage(query: AssetQuery, signal?: AbortSignal): Promise<AssetPage>;
  create(request: AssetCreateRequest, signal?: AbortSignal): Promise<AssetItem>;
  update(id: number, name: string, signal?: AbortSignal): Promise<AssetItem>;
  archive(id: number, signal?: AbortSignal): Promise<void>;
}
