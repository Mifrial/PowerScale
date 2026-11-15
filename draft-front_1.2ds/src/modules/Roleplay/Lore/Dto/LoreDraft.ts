import type { LoreArticleInput } from '@/modules/Roleplay/Lore/Dto/LoreArticleInput';

/** Локальный черновик статьи в localStorage. */
export interface LoreDraft {
  article: LoreArticleInput;
  expectedVersion?: number;
  savedAt: number;
}
