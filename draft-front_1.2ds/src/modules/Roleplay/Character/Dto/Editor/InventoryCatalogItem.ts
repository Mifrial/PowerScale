import type { InventoryItemType } from '@/modules/Roleplay/Character/Enum/InventoryItemType';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';

export type InventoryCatalogItem = {
  ruleCode: string;
  name: string;
  description: string;
  cost: number;
  section: string | null;
  subtitle: string;
  type: InventoryItemType;
  spec: ItemSpec | undefined;
  featureKeywords: { id: number; name: string }[];
  keywordIds: number[];
};
