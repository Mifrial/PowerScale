import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { IRuleApi } from '@/modules/Roleplay/Rule/Interface/IRuleApi';
import type { IRevisionRulesFetcher } from '@/modules/Roleplay/Rule/Interface/IRevisionRulesFetcher';
import { revisionRulesFetcherRegistry } from '@/modules/Roleplay/Rule/Service/Instance/revisionRulesFetcherRegistry';
import { registerPermissionCategory } from '@/modules/Core/User/init';
import { RULE_PERMISSION_CATEGORY } from '@/modules/Roleplay/Rule/Constant/Permission/RULE_PERMISSION_CATEGORY';

export { ruleValidationService } from '@/modules/Roleplay/Rule/Service/Instance/ruleValidationService';
export { ruleDiffService } from '@/modules/Roleplay/Rule/Service/Instance/ruleDiffService';
export { itemModifierService } from '@/modules/Roleplay/Rule/Service/Instance/itemModifierService';
export { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';
export { checkLaunchService } from '@/modules/Roleplay/Rule/Service/Instance/checkLaunchService';
export { checkSuccessRatingService } from '@/modules/Roleplay/Rule/Service/Instance/checkSuccessRatingService';
export { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';
export { spellDamageService } from '@/modules/Roleplay/Rule/Service/Instance/spellDamageService';
export { spellDurationLabelService } from '@/modules/Roleplay/Rule/Service/Instance/spellDurationLabelService';
export { abilityTypeChipLabelService } from '@/modules/Roleplay/Rule/Service/Instance/abilityTypeChipLabelService';
export { derivedCharacteristicService } from '@/modules/Roleplay/Rule/Service/Instance/derivedCharacteristicService';
export { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/Service/Instance/aggregateSourceDeltasService';
export { advantageDropService } from '@/modules/Roleplay/Rule/Service/Instance/advantageDropService';
export { formatStateEffectsService } from '@/modules/Roleplay/Rule/Service/Instance/formatStateEffectsService';
export { raceSpecService } from '@/modules/Roleplay/Rule/Service/Instance/raceSpecService';
export { RaceSpecService } from '@/modules/Roleplay/Rule/Service/Spec/RaceSpecService';
export { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/Instance/ruleReferenceService';
export { actionEffectLabelService } from '@/modules/Roleplay/Rule/Service/Instance/actionEffectLabelService';
export { movementDistanceExpressionService } from '@/modules/Roleplay/Rule/Service/Instance/movementDistanceExpressionService';
export { parameterLimitName } from '@/modules/Roleplay/Rule/Utils/parameterLimitName';
export { resourceShortName } from '@/modules/Roleplay/Rule/Utils/resourceShortName';
export { slugify } from '@/modules/Roleplay/Rule/Utils/Text/slugify';
export { useRuleHostContext } from '@/modules/Roleplay/Rule/Composables/useRuleHostContext';
export { useRuleDrafts } from '@/modules/Roleplay/Rule/Composables/useRuleDrafts';
export { ruleContentStatusService } from '@/modules/Roleplay/Rule/Service/Instance/ruleContentStatusService';

export function registerRuleApi(api: IRuleApi): void {
  serviceLocator.set('Roleplay.Rule.Service.RuleApi', api);
}

export function getRuleApi(): IRuleApi {
  return serviceLocator.get('Roleplay.Rule.Service.RuleApi');
}

export function registerRevisionRulesFetcher(fetcher: IRevisionRulesFetcher): void {
  revisionRulesFetcherRegistry.register(fetcher);
}

export function getRevisionRulesFetcher(): IRevisionRulesFetcher | null {
  return revisionRulesFetcherRegistry.get();
}

export async function registerRuleModule(): Promise<void> {
  registerPermissionCategory(RULE_PERMISSION_CATEGORY);
  const { registerRuleChatPlugins } = await import('@/modules/Roleplay/Rule/Service/Instance/ruleChatPlugins');
  registerRuleChatPlugins();
}
