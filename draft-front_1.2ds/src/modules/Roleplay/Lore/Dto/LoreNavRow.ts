import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';

/** Строка плоской навигации по дереву кодекса. */
export type LoreNavRow =
  | { kind: 'section'; depth: number; code: string; name: string; icon: string }
  | { kind: 'article'; depth: number; article: LoreArticleSummary };
