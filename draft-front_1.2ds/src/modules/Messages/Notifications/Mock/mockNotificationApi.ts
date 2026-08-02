import type { INotificationApi } from '../Interface/INotificationApi'
import * as mock from './mockNotifications'

export const mockNotificationApi: INotificationApi = {
  fetchPage: mock.mockFetchNotificationsPage,
  markAsRead: mock.mockMarkAsRead,
  markAllAsRead: mock.mockMarkAllAsRead,
}
