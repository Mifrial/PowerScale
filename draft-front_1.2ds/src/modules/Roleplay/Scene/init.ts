import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerPermissionCategory } from '@/modules/Core/User/init';
import { registerMenuItem } from '@/modules/Core/UI/init';
import type { ISceneApi } from '@/modules/Roleplay/Scene/Interface/ISceneApi';
import { SCENE_PERMISSION_CATEGORY } from '@/modules/Roleplay/Scene/Constant/Permission/SCENE_PERMISSION_CATEGORY';
import { SCENES_MENU_ITEM } from '@/modules/Roleplay/Scene/Constant/Navigation/SCENES_MENU_ITEM';

export function registerSceneApi(api: ISceneApi): void {
  serviceLocator.set('Roleplay.Scene.Service.SceneApi', api);
}

export function getSceneApi(): ISceneApi {
  return serviceLocator.get('Roleplay.Scene.Service.SceneApi');
}

export function registerSceneModule(): void {
  registerPermissionCategory(SCENE_PERMISSION_CATEGORY);
  registerMenuItem(SCENES_MENU_ITEM);
}

export { sceneViewportService } from '@/modules/Roleplay/Scene/Service/Instance/sceneViewportService';
export { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
export { sceneHitTestService } from '@/modules/Roleplay/Scene/Service/Instance/sceneHitTestService';
export { createPixiSceneRenderer } from '@/modules/Roleplay/Scene/Service/Instance/pixiSceneRenderer';
