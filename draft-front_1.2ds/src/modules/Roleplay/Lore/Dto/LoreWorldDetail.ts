import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';
import type { LoreSection } from '@/modules/Roleplay/Lore/Dto/LoreSection';
import type { LoreWorld } from '@/modules/Roleplay/Lore/Dto/LoreWorld';

/** Мир с деревом разделов и доступными статьями. */
export interface LoreWorldDetail extends LoreWorld {
  sections: LoreSection[];
  articles: LoreArticleSummary[];
  sectionCount: number;
  articleCount: number;
}
