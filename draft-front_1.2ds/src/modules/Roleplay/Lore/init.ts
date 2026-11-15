import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory } from '@/modules/Core/User/init';
import { registerMenuItem } from '@/modules/Core/UI/init';
import type { ILoreApi } from '@/modules/Roleplay/Lore/Interface/ILoreApi';
import { LORE_MENU_ITEM } from '@/modules/Roleplay/Lore/Constant/Navigation/LORE_MENU_ITEM';
import { LORE_PERMISSION_CATEGORY } from '@/modules/Roleplay/Lore/Constant/Permission/LORE_PERMISSION_CATEGORY';

export function registerLoreApi(api: ILoreApi): void {
  serviceLocator.set('Roleplay.Lore.Service.LoreApi', api);
}

export function getLoreApi(): ILoreApi {
  return serviceLocator.get('Roleplay.Lore.Service.LoreApi');
}

export function registerLoreModule(): void {
  registerPermissionCategory(LORE_PERMISSION_CATEGORY);
  registerMenuItem(LORE_MENU_ITEM);
}

export { loreTocService } from '@/modules/Roleplay/Lore/Service/Instance/loreTocService';
