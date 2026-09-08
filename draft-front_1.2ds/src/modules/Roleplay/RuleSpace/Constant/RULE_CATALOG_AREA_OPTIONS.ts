import type { RuleCatalogArea } from '@/modules/Roleplay/RuleSpace/Enum/RuleCatalogArea';

export const RULE_CATALOG_AREA_OPTIONS: { title: string; value: RuleCatalogArea }[] = [
  { title: 'Основные правила', value: 'base' },
  { title: 'Расы', value: 'race' },
  { title: 'Личность', value: 'personality' },
  { title: 'Развитие', value: 'development' },
  { title: 'Инвентарь', value: 'inventory' },
];
