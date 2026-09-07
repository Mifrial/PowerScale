import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';

export const columns: ColumnDefinition[] = [
  { key: 'code', label: 'Код', type: 'string' },
  { key: 'version', label: 'Версия', type: 'string' },
  { key: 'name', label: 'Название', type: 'string', meta: { clickable: true } },
  { key: 'description', label: 'Описание', type: 'string' },
];
