import type { LoreArticleStatus } from '@/modules/Roleplay/Lore/Enum/LoreArticleStatus';
import type { LoreArticleVisibility } from '@/modules/Roleplay/Lore/Enum/LoreArticleVisibility';

/** Снимок статьи для сохранения. */
export interface LoreArticleInput {
  code: string;
  title: string;
  summary: string;
  bodyHtml: string;
  sectionCode: string | null;
  sortOrder: number;
  keywordIds: number[];
  status: LoreArticleStatus;
  visibility: LoreArticleVisibility;
  readers: number[];
}
