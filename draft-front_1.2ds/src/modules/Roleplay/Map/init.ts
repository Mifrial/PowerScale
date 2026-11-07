import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory } from '@/modules/Core/User/init';
import { registerMenuItem } from '@/modules/Core/UI/init';
import type { IMapApi } from '@/modules/Roleplay/Map/Interface/IMapApi';
import { MAP_PERMISSION_CATEGORY } from '@/modules/Roleplay/Map/Constant/Permission/MAP_PERMISSION_CATEGORY';
import { MAPS_MENU_ITEM } from '@/modules/Roleplay/Map/Constant/Navigation/MAPS_MENU_ITEM';

export function registerMapApi(api: IMapApi): void {
  serviceLocator.set('Roleplay.Map.Service.MapApi', api);
}

export function getMapApi(): IMapApi {
  return serviceLocator.get('Roleplay.Map.Service.MapApi');
}

export function registerMapModule(): void {
  registerPermissionCategory(MAP_PERMISSION_CATEGORY);
  registerMenuItem(MAPS_MENU_ITEM);
}

export { mapViewportService } from '@/modules/Roleplay/Map/Service/Instance/mapViewportService';
export { mapGeometryService } from '@/modules/Roleplay/Map/Service/Instance/mapGeometryService';
export { mapRibbonService } from '@/modules/Roleplay/Map/Service/Instance/mapRibbonService';
export { mapRenderService } from '@/modules/Roleplay/Map/Service/Instance/mapRenderService';
export { mapHitTestService } from '@/modules/Roleplay/Map/Service/Instance/mapHitTestService';
