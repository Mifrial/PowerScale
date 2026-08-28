import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Контекст хоста пространства для страниц Rule: Space provide, Rule inject. Без Dto Space. */
export interface IRuleHostContext {
  spaceId: number | null;
  effectiveRules: Rule[];
  sections?: {
    code: string;
    name: string;
    parentCode: string | null;
    sortOrder: number;
    catalogRootFor?: 'base' | 'race' | 'personality' | 'development' | 'inventory';
  }[];
}
