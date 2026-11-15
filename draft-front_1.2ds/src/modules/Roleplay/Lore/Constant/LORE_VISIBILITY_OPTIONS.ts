import type { LoreArticleVisibility } from '@/modules/Roleplay/Lore/Enum/LoreArticleVisibility';
import type { LoreWorldVisibility } from '@/modules/Roleplay/Lore/Enum/LoreWorldVisibility';

export const LORE_WORLD_VISIBILITY_OPTIONS: { value: LoreWorldVisibility; title: string }[] = [
  { value: 'private', title: 'Приватный' },
  { value: 'public', title: 'Публичный' },
];

export const LORE_ARTICLE_VISIBILITY_OPTIONS: { value: LoreArticleVisibility; title: string }[] = [
  { value: 'public', title: 'Публичная' },
  { value: 'secret', title: 'Секретная' },
];
