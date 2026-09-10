import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { IRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/Interface/IRuleSpaceApi';
import { registerPermissionCategory } from '@/modules/Core/User/init';
import { registerMenuItem } from '@/modules/Core/UI/init';
import { SPACES_MENU_ITEM } from '@/modules/Roleplay/RuleSpace/Constant/Navigation/SPACES_MENU_ITEM';
import { SPACE_PERMISSION_CATEGORY } from '@/modules/Roleplay/RuleSpace/Constant/permissions';
import { registerRevisionRulesFetcher } from '@/modules/Roleplay/Rule/init';
import { useSpaceRevisionStore } from '@/modules/Roleplay/RuleSpace/Store/spaceRevision';

export function registerRuleSpaceApi(api: IRuleSpaceApi): void {
  serviceLocator.set('Roleplay.RuleSpace.Service.RuleSpaceApi', api);
}

export function getRuleSpaceApi(): IRuleSpaceApi {
  return serviceLocator.get('Roleplay.RuleSpace.Service.RuleSpaceApi');
}

export { ACTUAL_RULES_SPACE_CODE } from '@/modules/Roleplay/RuleSpace/Constant/ACTUAL_RULES_SPACE_CODE';
export { spaceContextKey } from '@/modules/Roleplay/RuleSpace/Constant/spaceContextKey';
export { useSpaceContext } from '@/modules/Roleplay/RuleSpace/Composables/useSpaceContext';
export { useSpaceCatalog } from '@/modules/Roleplay/RuleSpace/Composables/useSpaceCatalog';
export { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/Composables/useSpaceRevision';
export type { ISpaceContext } from '@/modules/Roleplay/RuleSpace/Interface/ISpaceContext';
export { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/Instance/abilitySectionTreeService';

export function registerRuleSpaceModule(): void {
  registerPermissionCategory(SPACE_PERMISSION_CATEGORY);
  registerMenuItem(SPACES_MENU_ITEM);
  registerRevisionRulesFetcher({
    fetchRules: async (spaceId, revision, signal) => {
      const slice = await useSpaceRevisionStore().fetchRevision(spaceId, revision, signal);

      return slice.rules;
    },
  });
}
