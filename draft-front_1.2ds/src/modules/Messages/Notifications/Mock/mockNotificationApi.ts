import type { INotificationApi } from '@/modules/Messages/Notifications/Interface/INotificationApi'
import * as mock from '@/modules/Messages/Notifications/Mock/mockNotifications'

export const mockNotificationApi: INotificationApi = {
  fetchPage: mock.mockFetchNotificationsPage,
  markAsRead: mock.mockMarkAsRead,
  markAllAsRead: mock.mockMarkAllAsRead,
}
