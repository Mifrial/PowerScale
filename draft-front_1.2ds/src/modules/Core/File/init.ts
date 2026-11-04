import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory, registerAdminSection, accessService } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';
import type { IFileApi } from '@/modules/Core/File/Interface/IFileApi';
import { FILE_PERMISSION_CATEGORY } from '@/modules/Core/File/Constant/Permission/FILE_PERMISSION_CATEGORY';
import { FILES_ADMIN_SECTION } from '@/modules/Core/File/Constant/Permission/FILES_ADMIN_SECTION';
import { FILES_MENU_ITEM } from '@/modules/Core/File/Constant/Navigation/FILES_MENU_ITEM';
import { registerMenuContribution, registerMenuItem } from '@/modules/Core/UI/init';

export function registerFileApi(api: IFileApi): void {
  serviceLocator.set('Core.File.Service.FileApi', api);
}

export function getFileApi(): IFileApi {
  return serviceLocator.get('Core.File.Service.FileApi');
}

export function registerFileModule(): void {
  registerPermissionCategory(FILE_PERMISSION_CATEGORY);
  registerAdminSection(FILES_ADMIN_SECTION);
  registerMenuContribution({
    id: 'file.admin',
    apply: (actor) => {
      const user = actor === null || actor === undefined ? null : (actor as User);
      if (!accessService.hasAnyPermission(user, ['file.view'])) {
        return;
      }
      registerMenuItem(FILES_MENU_ITEM);
    },
  });
}
