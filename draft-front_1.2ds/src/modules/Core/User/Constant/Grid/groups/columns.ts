import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';

export const columns: ColumnDefinition[] = [
  { key: 'name', label: 'Название', type: 'string', meta: { clickable: true } },
  { key: 'memberCount', label: 'Участники', type: 'number' },
  {
    key: 'active',
    label: 'Статус',
    type: 'boolean',
    meta: {
      trueLabel: 'Активна',
      falseLabel: 'Деактивирована',
      trueIcon: 'mdi-check-circle',
      falseIcon: 'mdi-cancel',
      trueColor: 'success',
      falseColor: 'grey',
    },
  },
];
