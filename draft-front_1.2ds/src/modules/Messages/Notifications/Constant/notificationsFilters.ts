import type { NotifFilter } from '@/modules/Messages/Notifications/Enum/NotifFilter';

export const filters: { key: NotifFilter; label: string }[] = [
  { key: 'all', label: 'Все' },
  { key: 'unread', label: 'Не просмотренные' },
  { key: 'action', label: 'Ожидают действия' },
];
