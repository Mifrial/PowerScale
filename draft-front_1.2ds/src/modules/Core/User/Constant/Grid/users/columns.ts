import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';

export const columns: ColumnDefinition[] = [
  { key: 'name', label: 'Имя', type: 'string', sortable: false, meta: { clickable: true } },
  { key: 'surname', label: 'Фамилия', type: 'string', sortable: false },
  { key: 'nickname', label: 'Псевдоним', type: 'string', sortable: false },
  { key: 'login', label: 'Логин', type: 'string', sortable: false },
  { key: 'email', label: 'Email', type: 'string', sortable: false },
  { key: 'active', label: 'Активность', type: 'active', sortable: false },
];
