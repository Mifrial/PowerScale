import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';
import type { LoreSection } from '@/modules/Roleplay/Lore/Dto/LoreSection';

/** Узел навигации: раздел, его дети и статьи раздела. */
export interface LoreTreeNode {
  section: LoreSection;
  children: LoreTreeNode[];
  articles: LoreArticleSummary[];
}
