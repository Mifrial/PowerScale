import type { LoreArticleInput } from '@/modules/Roleplay/Lore/Dto/LoreArticleInput';

/** Запрос `lore.saveArticle`: без expectedVersion — создание. */
export interface SaveLoreArticleData {
  worldCode: string;
  article: LoreArticleInput;
  expectedVersion?: number;
}
