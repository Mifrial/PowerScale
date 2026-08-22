import type { Notification } from '@/modules/Messages/Notifications/Dto/Notification';

export interface NotificationPage {
  items: Notification[];
  total: number;
  unreadCount: number;
}
