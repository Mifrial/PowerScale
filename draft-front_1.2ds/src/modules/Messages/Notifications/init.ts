import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator'
import type { INotificationApi } from '@/modules/Messages/Notifications/Interface/INotificationApi'
import type { INotificationTemplateApi } from '@/modules/Messages/Notifications/Interface/INotificationTemplateApi'
import { registerPermissionCategory, registerAdminSection } from '@/modules/Core/User/init'
import { NOTIFICATION_TEMPLATE_PERMISSION_CATEGORY, TEMPLATES_ADMIN_SECTION } from '@/modules/Messages/Notifications/Constant/permissions'

export function registerNotificationApi(api: INotificationApi): void {
  serviceLocator.set('Messages.Notifications.Service.NotificationApi', api)
}

export function getNotificationApi(): INotificationApi {
  return serviceLocator.get('Messages.Notifications.Service.NotificationApi')
}

export function registerTemplateApi(api: INotificationTemplateApi): void {
  serviceLocator.set('Messages.Notifications.Service.TemplateApi', api)
}

export function getTemplateApi(): INotificationTemplateApi {
  return serviceLocator.get('Messages.Notifications.Service.TemplateApi')
}

export function registerNotificationModule(): void {
  registerPermissionCategory(NOTIFICATION_TEMPLATE_PERMISSION_CATEGORY)
  registerAdminSection(TEMPLATES_ADMIN_SECTION)
}

