import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';

export const GRANT_TYPES: { label: string; value: Grant['type'] }[] = [
  { label: 'Даёт характеристику', value: 'characteristic' },
  { label: 'Даёт характеристику (значение параметра X)', value: 'characteristic_parameter' },
  { label: 'Модификатор характеристики', value: 'characteristic_modify' },
  { label: 'Даёт ресурс', value: 'resource' },
  { label: 'Меняет лимит ресурса', value: 'resource_limit_change' },
  { label: 'Даёт способность', value: 'ability' },
  { label: 'Признак', value: 'keyword' },
  { label: 'Даёт предмет', value: 'item' },
  { label: 'Даёт путь волшебства', value: 'magic_path' },
  { label: 'Открывает изучение волшебства', value: 'magic_study' },
  { label: 'Сопротивление урону', value: 'resistance' },
  { label: 'Модификатор чувства', value: 'sense_modify' },
  { label: 'Множитель дистанции процесса', value: 'process_distance_multiplier' },
  { label: 'Модификатор состояния', value: 'state_modify' },
  { label: 'Преимущество на проверку', value: 'check_advantage' },
  { label: 'Стартовый капитал (деньги)', value: 'money' },
];
