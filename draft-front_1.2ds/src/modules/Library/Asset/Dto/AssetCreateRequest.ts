import type { AssetScope } from '@/modules/Library/Asset/Enum/AssetScope';

/** Вход asset.create. */
export interface AssetCreateRequest {
  collectionModule: string;
  collectionCode: string;
  filePublicId: string;
  scope: AssetScope;
  name?: string;
}
