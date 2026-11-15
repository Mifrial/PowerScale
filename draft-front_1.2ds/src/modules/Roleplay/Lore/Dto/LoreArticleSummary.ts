import type { LoreArticleStatus } from '@/modules/Roleplay/Lore/Enum/LoreArticleStatus';
import type { LoreArticleVisibility } from '@/modules/Roleplay/Lore/Enum/LoreArticleVisibility';

/** Статья без тела: узел дерева и строка списка. */
export interface LoreArticleSummary {
  id: number;
  code: string;
  title: string;
  summary: string;
  sectionCode: string | null;
  sortOrder: number;
  keywordIds: number[];
  status: LoreArticleStatus;
  visibility: LoreArticleVisibility;
  isArchived: boolean;
  version: number;
  updatedAt: number;
}
