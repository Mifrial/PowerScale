import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory, registerAdminSection } from '@/modules/Core/User/init';
import type { IMechanicApi } from '@/modules/Roleplay/Mechanic/Interface/IMechanicApi';
import type { MechanicHandler } from '@/modules/Roleplay/Mechanic/Interface/MechanicHandler';
import { MECHANIC_PERMISSION_CATEGORY } from '@/modules/Roleplay/Mechanic/Constant/Permission/MECHANIC_PERMISSION_CATEGORY';
import { MECHANICS_ADMIN_SECTION } from '@/modules/Roleplay/Mechanic/Constant/Permission/MECHANICS_ADMIN_SECTION';
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
}
