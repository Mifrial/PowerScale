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
  { title: 'Состояние', value: 'state' },
  { title: 'Яд', value: 'poison' },
  { title: 'Чувство', value: 'sense' },
  { title: 'Возраст', value: 'age' },
  { title: 'Язык', value: 'language' },
  { title: 'Семья оружия', value: 'weapon_family' },
  { title: 'Модификатор предмета', value: 'item_modifier' },
  { title: 'Тип модификатора предмета', value: 'item_modifier_type' },
];
