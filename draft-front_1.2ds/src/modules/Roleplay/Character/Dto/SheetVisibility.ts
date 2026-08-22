import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';

/** Аудитория зоны видимости: 'all' (все в контексте), 'gm' (токен роли, резолвится инъекцией), либо пользователи. */
export type SheetAudience = 'all' | 'gm' | number[];

/** Одно правило зоны видимости листа: аудитория + видимые секции. */
export interface SheetVisibilityRule {
  audience: SheetAudience;
  sections: SheetSection[];
}

/** Зоны видимости листа — объединение правил (любое подходящее даёт свои секции). */
export type SheetVisibility = SheetVisibilityRule[];
