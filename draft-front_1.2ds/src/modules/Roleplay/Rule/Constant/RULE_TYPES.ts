import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';

export const RULE_TYPES: { title: string; value: RuleType }[] = [
  { title: 'Простое правило', value: 'simple' },
  { title: 'Раса', value: 'race' },
  { title: 'Вид/Подвид', value: 'species' },
  { title: 'Характеристика', value: 'characteristic' },
  { title: 'Ресурс', value: 'resource' },
  { title: 'Очки', value: 'points' },
  { title: 'Способность', value: 'ability' },
  { title: 'Предмет', value: 'item' },
  { title: 'Тип урона', value: 'damage_type' },
];
