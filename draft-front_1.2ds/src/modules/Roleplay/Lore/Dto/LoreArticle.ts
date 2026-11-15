import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';

/** Полная статья с телом. */
export interface LoreArticle extends LoreArticleSummary {
  bodyHtml: string;
  readerIds: number[];
  createdBy: number;
  updatedBy: number | null;
  createdAt: number;
}
