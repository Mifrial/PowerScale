import type { RuleCatalogArea } from '@/modules/Roleplay/Space/Enum/RuleCatalogArea';

export interface AbilitySection {
  code: string;
  name: string;
  parentCode: string | null;
  sortOrder: number;
  catalogRootFor?: RuleCatalogArea;
}
