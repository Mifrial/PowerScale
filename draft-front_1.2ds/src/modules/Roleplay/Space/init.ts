import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { ISpaceApi } from '@/modules/Roleplay/Space/Interface/ISpaceApi';
import { registerPermissionCategory } from '@/modules/Core/User/init';
import { SPACE_PERMISSION_CATEGORY } from '@/modules/Roleplay/Space/Constant/permissions';
import { registerRevisionRulesFetcher } from '@/modules/Roleplay/Rule/init';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';

export function registerSpaceApi(api: ISpaceApi): void {
  serviceLocator.set('Roleplay.Space.Service.SpaceApi', api);
}

export function getSpaceApi(): ISpaceApi {
  return serviceLocator.get('Roleplay.Space.Service.SpaceApi');
}

export { ACTUAL_RULES_SPACE_CODE } from '@/modules/Roleplay/Space/Constant/ACTUAL_RULES_SPACE_CODE';
export { spaceContextKey } from '@/modules/Roleplay/Space/Constant/spaceContextKey';
export { useSpaceContext } from '@/modules/Roleplay/Space/Composables/useSpaceContext';
export type { ISpaceContext } from '@/modules/Roleplay/Space/Interface/ISpaceContext';

export function registerSpaceModule(): void {
  registerPermissionCategory(SPACE_PERMISSION_CATEGORY);
  registerRevisionRulesFetcher({
    fetchRules: async (spaceId, revision, signal) => {
      const slice = await useSpaceRevisionStore().fetchRevision(spaceId, revision, signal);

      return slice.rules;
    },
  });
}
