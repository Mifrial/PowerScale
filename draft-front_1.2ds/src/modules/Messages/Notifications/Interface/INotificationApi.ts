import type { Notification } from '@/modules/Messages/Notifications/Dto/Notification';
import type { NotifFilter } from '@/modules/Messages/Notifications/Enum/NotifFilter';

export interface NotificationFilters {
  filter?: NotifFilter;
  search?: string;
  offset: number;
  limit: number;
}

export interface NotificationPage {
  items: Notification[];
  total: number;
  unreadCount: number;
}

export interface INotificationApi {
  fetchPage(filters: NotificationFilters): Promise<NotificationPage>;
  markAsRead(id: number): Promise<void>;
  markAllAsRead(): Promise<void>;
}
