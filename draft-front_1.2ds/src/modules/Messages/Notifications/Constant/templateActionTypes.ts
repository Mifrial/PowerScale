import type { NotificationButton } from '@/modules/Messages/Notifications/Dto/NotificationButton';

export const actionTypes: { title: string; value: NotificationButton['actionType'] }[] = [
  { title: 'Событие', value: 'event' },
  { title: 'URL', value: 'url' },
  { title: 'Действие', value: 'action' },
];
