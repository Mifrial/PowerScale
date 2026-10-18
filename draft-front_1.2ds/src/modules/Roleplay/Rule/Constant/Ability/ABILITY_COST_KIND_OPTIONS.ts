import type { AbilityCostKind } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityCostKind';

export const ABILITY_COST_KIND_OPTIONS: { title: string; value: AbilityCostKind }[] = [
  { title: 'Массив уровней', value: 'array' },
  { title: 'Прогрессия', value: 'progression' },
  { title: 'Авто-получение', value: 'automatic' },
  { title: 'Параметр × ставка', value: 'parameter' },
  { title: 'Таблица параметра', value: 'parameter_table' },
  { title: 'Сумма таблиц параметров', value: 'parameter_sum_tables' },
];
