import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';

export const columns: ColumnDefinition[] = [
  { key: 'name', label: 'Имя', type: 'string', meta: { clickable: true } },
  { key: 'surname', label: 'Фамилия', type: 'string' },
  { key: 'nickname', label: 'Псевдоним', type: 'string' },
  { key: 'login', label: 'Логин', type: 'string' },
  { key: 'email', label: 'Email', type: 'string' },
  { key: 'active', label: 'Активность', type: 'active', sortable: true },
  { key: 'lastLogin', label: 'Последний вход', type: 'date' },
];
