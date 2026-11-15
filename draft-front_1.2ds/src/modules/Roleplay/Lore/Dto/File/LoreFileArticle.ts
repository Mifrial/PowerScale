import type { LoreArticleStatus } from '@/modules/Roleplay/Lore/Enum/LoreArticleStatus';
import type { LoreArticleVisibility } from '@/modules/Roleplay/Lore/Enum/LoreArticleVisibility';

/** Статья в файле `powerscale.lore`. */
export interface LoreFileArticle {
  code: string;
  title: string;
  summary: string;
  bodyHtml: string;
  sectionCode: string | null;
  sortOrder: number;
  keywordCodes: string[];
  status: LoreArticleStatus;
  visibility: LoreArticleVisibility;
  readers: string[];
  isArchived: boolean;
}
