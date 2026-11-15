import type { LoreArticleStatus } from '@/modules/Roleplay/Lore/Enum/LoreArticleStatus';

export const LORE_STATUS_OPTIONS: { value: LoreArticleStatus; title: string }[] = [
  { value: 'draft', title: 'Черновик' },
  { value: 'published', title: 'Опубликована' },
];
