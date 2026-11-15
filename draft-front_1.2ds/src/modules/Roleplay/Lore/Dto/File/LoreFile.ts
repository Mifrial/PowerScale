import type { LoreFileArticle } from '@/modules/Roleplay/Lore/Dto/File/LoreFileArticle';
import type { LoreFileKeyword } from '@/modules/Roleplay/Lore/Dto/File/LoreFileKeyword';
import type { LoreFileSection } from '@/modules/Roleplay/Lore/Dto/File/LoreFileSection';
import type { LoreFileSource } from '@/modules/Roleplay/Lore/Dto/File/LoreFileSource';
import type { LoreFileWorld } from '@/modules/Roleplay/Lore/Dto/File/LoreFileWorld';

/** Документ `powerscale.lore` v1. */
export interface LoreFile {
  format: 'powerscale.lore';
  formatVersion: 1;
  exportedAt: number;
  world: LoreFileWorld;
  source: LoreFileSource;
  sections: LoreFileSection[];
  keywords: LoreFileKeyword[];
  articles: LoreFileArticle[];
}
