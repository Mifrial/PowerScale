import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Срез ревизии для деталки правила: само правило и каталог для подписей ссылок. */
export interface RevisionRuleSlice {
  rule: Rule | null;
  rules: Rule[];
}
