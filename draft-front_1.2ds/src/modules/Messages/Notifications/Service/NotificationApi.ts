import type { INotificationApi } from '@/modules/Messages/Notifications/Interface/INotificationApi';
import type { NotificationFilters } from '@/modules/Messages/Notifications/Dto/NotificationFilters';
import type { NotificationPage } from '@/modules/Messages/Notifications/Dto/NotificationPage';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';

export class NotificationApi implements INotificationApi {
  constructor(private readonly engine: Engine) {}

  async fetchPage(filters: NotificationFilters): Promise<NotificationPage> {
    const res = await this.engine.runAction<NotificationPage>('notifications.fetchPage', filters);
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to fetch notifications');

    return res.data;
  }

  async markAsRead(id: number): Promise<void> {
    await this.engine.runAction('notifications.markAsRead', { id });
  }

  async markAllAsRead(): Promise<void> {
    await this.engine.runAction('notifications.markAllAsRead');
  }
}
