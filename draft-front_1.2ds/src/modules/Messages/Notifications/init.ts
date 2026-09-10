import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { defineAsyncComponent } from 'vue';
import type { INotificationApi } from '@/modules/Messages/Notifications/Interface/INotificationApi';
import type { INotificationTemplateApi } from '@/modules/Messages/Notifications/Interface/INotificationTemplateApi';
import { registerPermissionCategory, registerAdminSection, accessService } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';
import { NOTIFICATION_TEMPLATE_PERMISSION_CATEGORY } from '@/modules/Messages/Notifications/Constant/Permission/NOTIFICATION_TEMPLATE_PERMISSION_CATEGORY';
import { TEMPLATES_ADMIN_SECTION } from '@/modules/Messages/Notifications/Constant/Permission/TEMPLATES_ADMIN_SECTION';
import { NOTIFICATIONS_MENU_ITEM } from '@/modules/Messages/Notifications/Constant/Navigation/NOTIFICATIONS_MENU_ITEM';
import { TEMPLATES_MENU_ITEM } from '@/modules/Messages/Notifications/Constant/Navigation/TEMPLATES_MENU_ITEM';
import { registerMenuContribution, registerMenuItem } from '@/modules/Core/UI/init';

export function registerNotificationApi(api: INotificationApi): void {
  serviceLocator.set('Messages.Notifications.Service.NotificationApi', api);
}

export function getNotificationApi(): INotificationApi {
  return serviceLocator.get('Messages.Notifications.Service.NotificationApi');
}

export function registerTemplateApi(api: INotificationTemplateApi): void {
  serviceLocator.set('Messages.Notifications.Service.TemplateApi', api);
}

export function getTemplateApi(): INotificationTemplateApi {
  return serviceLocator.get('Messages.Notifications.Service.TemplateApi');
}

export function registerNotificationModule(): void {
  registerPermissionCategory(NOTIFICATION_TEMPLATE_PERMISSION_CATEGORY);
  registerAdminSection(TEMPLATES_ADMIN_SECTION);
  registerMenuItem(NOTIFICATIONS_MENU_ITEM);
  registerMenuContribution({
    id: 'notification.templates',
    apply: (actor) => {
      const user = actor === null || actor === undefined ? null : (actor as User);
      if (!accessService.hasAnyPermission(user, ['notification_template.view'])) {
        return;
      }
      registerMenuItem(TEMPLATES_MENU_ITEM);
    },
  });
}

export { useNotificationInbox } from '@/modules/Messages/Notifications/Composables/useNotificationInbox';

export const NotificationList = defineAsyncComponent(
  () => import('@/modules/Messages/Notifications/Component/NotificationList.vue'),
);
