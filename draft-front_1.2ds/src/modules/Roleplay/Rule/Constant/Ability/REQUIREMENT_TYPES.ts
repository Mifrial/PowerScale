import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';

export const REQUIREMENT_TYPES: { label: string; value: Requirement['type']; description: string }[] = [
  { label: 'Есть способность', value: 'has_ability', description: 'Персонаж владеет способностью (не ниже уровня)' },
  {
    label: 'N способностей с признаком',
    value: 'has_ability_keyword',
    description: 'Количество способностей с указанным признаком',
  },
  { label: 'Есть признак', value: 'has_keyword', description: 'У персонажа есть признак (просто есть/нет)' },
  {
    label: 'Характеристика >= min',
    value: 'characteristic_value',
    description: 'Значение характеристики не ниже указанного',
  },
  { label: 'Ресурс / лимит', value: 'resource_limit', description: 'Ресурс есть или его лимит не ниже указанного' },
  { label: 'Есть путь волшебства', value: 'has_magic_path', description: 'Персонажу дарован указанный путь' },
  {
    label: 'Опыт пути волшебства',
    value: 'magic_path_experience',
    description: 'Сумма стоимостей способностей этого пути не ниже указанной',
  },
  { label: 'И', value: 'and', description: 'Все условия внутри должны выполниться' },
  { label: 'ИЛИ', value: 'or', description: 'Хотя бы одно условие внутри должно выполниться' },
];
