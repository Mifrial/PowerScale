import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';

export const columns: ColumnDefinition[] = [
  { key: 'createdAt', label: 'Время', type: 'datetime', sortable: false },
  { key: 'level', label: 'Уровень', type: 'string', sortable: false },
  { key: 'source', label: 'Источник', type: 'string', sortable: false },
  { key: 'message', label: 'Сообщение', type: 'string', sortable: false },
  { key: 'errorCode', label: 'Код', type: 'string', sortable: false },
  { key: 'userId', label: 'Пользователь', type: 'number', sortable: false },
];
