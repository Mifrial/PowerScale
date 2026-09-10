import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory, registerAdminSection, accessService } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';
import type { IMechanicApi } from '@/modules/Roleplay/Mechanic/Interface/IMechanicApi';
import type { MechanicHandler } from '@/modules/Roleplay/Mechanic/Interface/MechanicHandler';
import { MECHANIC_PERMISSION_CATEGORY } from '@/modules/Roleplay/Mechanic/Constant/Permission/MECHANIC_PERMISSION_CATEGORY';
import { MECHANICS_ADMIN_SECTION } from '@/modules/Roleplay/Mechanic/Constant/Permission/MECHANICS_ADMIN_SECTION';
import { MECHANICS_MENU_ITEM } from '@/modules/Roleplay/Mechanic/Constant/Navigation/MECHANICS_MENU_ITEM';
import { registerMenuContribution, registerMenuItem } from '@/modules/Core/UI/init';
import { mechanicHandlerRegistry } from '@/modules/Roleplay/Mechanic/Service/Instance/mechanicHandlerRegistry';

export { useMechanics } from '@/modules/Roleplay/Mechanic/Composables/useMechanics';
export { mechanicEngine } from '@/modules/Roleplay/Mechanic/Service/Instance/mechanicEngine';
export { MechanicEngine } from '@/modules/Roleplay/Mechanic/Service/MechanicEngine';
export { MechanicHandlerRegistry } from '@/modules/Roleplay/Mechanic/Service/MechanicHandlerRegistry';
export { ROLL_EVENTS } from '@/modules/Roleplay/Mechanic/Constant/ROLL_EVENTS';
export { PURCHASE_SURCHARGE_EVENT } from '@/modules/Roleplay/Mechanic/Constant/PURCHASE_SURCHARGE_EVENT';

export function registerMechanicApi(api: IMechanicApi): void {
  serviceLocator.set('Roleplay.Mechanic.Service.MechanicApi', api);
}

export function getMechanicApi(): IMechanicApi {
  return serviceLocator.get('Roleplay.Mechanic.Service.MechanicApi');
}

export function registerMechanicHandler(handler: MechanicHandler): void {
  mechanicHandlerRegistry.register(handler);
}

export function registerMechanicModule(): void {
  registerPermissionCategory(MECHANIC_PERMISSION_CATEGORY);
  registerAdminSection(MECHANICS_ADMIN_SECTION);
  registerMenuContribution({
    id: 'mechanic.admin',
    apply: (actor) => {
      const user = actor === null || actor === undefined ? null : (actor as User);
      if (!accessService.hasAnyPermission(user, ['mechanic.view'])) {
        return;
      }
      registerMenuItem(MECHANICS_MENU_ITEM);
    },
  });
}
