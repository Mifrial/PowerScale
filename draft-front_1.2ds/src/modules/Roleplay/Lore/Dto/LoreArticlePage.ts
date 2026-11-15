import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';

/** Страница статей. */
export interface LoreArticlePage {
  items: LoreArticleSummary[];
  total: number;
}
