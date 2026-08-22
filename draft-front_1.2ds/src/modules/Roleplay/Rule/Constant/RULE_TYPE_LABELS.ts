import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';

export const RULE_TYPE_LABELS: Record<RuleType, string> = {
  simple: 'Простое',
  race: 'Раса',
  species: 'Вид/Подвид',
  characteristic: 'Характеристика',
  resource: 'Ресурс',
  points: 'Очки',
  ability: 'Способность',
  item: 'Предмет',
  damage_type: 'Тип урона',
  source: 'Источник',
  state: 'Состояние',
  poison: 'Яд',
  sense: 'Чувство',
  age: 'Возраст',
  language: 'Язык',
  weapon_family: 'Семья оружия',
  item_modifier: 'Модификатор предмета',
  item_modifier_type: 'Тип модификатора предмета',
};
