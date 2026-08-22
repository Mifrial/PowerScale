import type { AgeEffect } from '@/modules/Roleplay/Rule/Dto/Age/AgeEffect';

/** Возрастная ступень (тип правила 'age'): ОЛ и лимит числа особенностей личности, эффекты. */
export interface Age {
  /** Имя ступени (на него ссылается таблица лет расы {age, ageStart, ageEnd}). */
  name: string;
  /** ОЛ, даваемые ступенью. */
  ol: number;
  /** Лимит числа особенностей личности (без признака «Богатство»). */
  featureLimit: number;
  /** Эффекты возраста — модификаторы характеристик (live в редакторе). */
  effects: AgeEffect[];
}
