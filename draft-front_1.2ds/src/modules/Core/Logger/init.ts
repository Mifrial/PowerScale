import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory, registerAdminSection, accessService } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';
import type { ILoggerApi } from '@/modules/Core/Logger/Interface/ILoggerApi';
import { LOGGER_PERMISSION_CATEGORY } from '@/modules/Core/Logger/Constant/Permission/LOGGER_PERMISSION_CATEGORY';
import { LOGS_ADMIN_SECTION } from '@/modules/Core/Logger/Constant/Permission/LOGS_ADMIN_SECTION';
import { LOGS_MENU_ITEM } from '@/modules/Core/Logger/Constant/Navigation/LOGS_MENU_ITEM';
import { registerMenuContribution, registerMenuItem } from '@/modules/Core/UI/init';

export function registerLoggerApi(api: ILoggerApi): void {
  serviceLocator.set('Core.Logger.Service.LoggerApi', api);
}

export function getLoggerApi(): ILoggerApi {
  return serviceLocator.get('Core.Logger.Service.LoggerApi');
}

export function registerLoggerModule(): void {
  registerPermissionCategory(LOGGER_PERMISSION_CATEGORY);
  registerAdminSection(LOGS_ADMIN_SECTION);
  registerMenuContribution({
    id: 'logger.admin',
    apply: (actor) => {
      const user = actor === null || actor === undefined ? null : (actor as User);
      if (!accessService.hasAnyPermission(user, ['logger.view'])) {
        return;
      }
      registerMenuItem(LOGS_MENU_ITEM);
    },
  });
}
