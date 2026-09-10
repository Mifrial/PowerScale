import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory, registerAdminSection } from '@/modules/Core/User/init';
import type { ILoggerApi } from '@/modules/Core/Logger/Interface/ILoggerApi';
import { LOGGER_PERMISSION_CATEGORY } from '@/modules/Core/Logger/Constant/Permission/LOGGER_PERMISSION_CATEGORY';
import { LOGS_ADMIN_SECTION } from '@/modules/Core/Logger/Constant/Permission/LOGS_ADMIN_SECTION';

export function registerLoggerApi(api: ILoggerApi): void {
  serviceLocator.set('Core.Logger.Service.LoggerApi', api);
}

export function getLoggerApi(): ILoggerApi {
  return serviceLocator.get('Core.Logger.Service.LoggerApi');
}

export function registerLoggerModule(): void {
  registerPermissionCategory(LOGGER_PERMISSION_CATEGORY);
  registerAdminSection(LOGS_ADMIN_SECTION);
}
