import type { LoreArticleSort } from '@/modules/Roleplay/Lore/Enum/LoreArticleSort';
import type { LoreArticleStatus } from '@/modules/Roleplay/Lore/Enum/LoreArticleStatus';
import type { LoreArticleVisibility } from '@/modules/Roleplay/Lore/Enum/LoreArticleVisibility';

/** Запрос страницы статей мира. */
export interface LoreArticleQuery {
  worldCode: string;
  limit: number;
  offset: number;
  q?: string;
  sectionCode?: string;
  keywordIds?: number[];
  status?: LoreArticleStatus;
  visibility?: LoreArticleVisibility;
  sort?: LoreArticleSort;
  includeArchived?: boolean;
}
