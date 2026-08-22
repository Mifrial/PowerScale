import type { NotificationFilters } from '@/modules/Messages/Notifications/Dto/NotificationFilters';
import type { NotificationPage } from '@/modules/Messages/Notifications/Dto/NotificationPage';

export interface INotificationApi {
  fetchPage(filters: NotificationFilters): Promise<NotificationPage>;
  markAsRead(id: number): Promise<void>;
  markAllAsRead(): Promise<void>;
}
