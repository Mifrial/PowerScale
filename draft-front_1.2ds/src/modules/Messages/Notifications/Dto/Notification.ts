import type { NotificationAction } from '@/modules/Messages/Notifications/Dto/NotificationAction';

export interface Notification {
  id: number;
  title: string;
  preview: string;
  createdAt: string;
  icon: string;
  read: boolean;
  actions: NotificationAction[];
}
