import type { AssetScope } from '@/modules/Library/Asset/Enum/AssetScope';

/** Плоский HTTP-контракт asset.findPage. */
export interface AssetQuery {
  collectionModule: string;
  collectionCode: string;
  scope: AssetScope;
  q?: string;
  limit: number;
  offset: number;
}
