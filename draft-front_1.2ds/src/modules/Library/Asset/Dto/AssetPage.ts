import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';

/** Страница ассетов одной коллекции. */
export interface AssetPage {
  items: AssetItem[];
  total: number;
}
