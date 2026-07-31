import { sl } from '@/modules/Core/Engine/ServiceLocator'
import type { INotificationApi } from '@/modules/Messages/Notifications/Interface/INotificationApi'
import type { INotificationTemplateApi } from '@/modules/Messages/Notifications/Interface/INotificationTemplateApi'

export function registerNotificationApi(api: INotificationApi): void {
  sl.set('Messages.Notifications.Service.NotificationApi', api)
}

export function getNotificationApi(): INotificationApi {
  return sl.get('Messages.Notifications.Service.NotificationApi')
}

export function registerTemplateApi(api: INotificationTemplateApi): void {
  sl.set('Messages.Notifications.Service.TemplateApi', api)
}

export function getTemplateApi(): INotificationTemplateApi {
  return sl.get('Messages.Notifications.Service.TemplateApi')
}
