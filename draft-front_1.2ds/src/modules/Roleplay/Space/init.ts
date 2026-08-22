import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { ISpaceApi } from '@/modules/Roleplay/Space/Interface/ISpaceApi';
import { registerPermissionCategory } from '@/modules/Core/User/init';
import { SPACE_PERMISSION_CATEGORY } from '@/modules/Roleplay/Space/Constant/permissions';

export function registerSpaceApi(api: ISpaceApi): void {
  serviceLocator.set('Roleplay.Space.Service.SpaceApi', api);
}

export function getSpaceApi(): ISpaceApi {
  return serviceLocator.get('Roleplay.Space.Service.SpaceApi');
}

export { spaceContextKey } from '@/modules/Roleplay/Space/Constant/spaceContextKey';
export { useSpaceContext } from '@/modules/Roleplay/Space/Composables/useSpaceContext';
export type { ISpaceContext } from '@/modules/Roleplay/Space/Interface/ISpaceContext';

export function registerSpaceModule(): void {
  registerPermissionCategory(SPACE_PERMISSION_CATEGORY);
}
