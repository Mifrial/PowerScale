import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { defineAsyncComponent } from 'vue';
import type { IRuleApi } from '@/modules/Roleplay/Rule/Interface/IRuleApi';
import type { IKeywordApi } from '@/modules/Roleplay/Rule/Interface/IKeywordApi';
import type { IRevisionRulesFetcher } from '@/modules/Roleplay/Rule/Interface/IRevisionRulesFetcher';
import { registerPermissionCategory, registerAdminSection } from '@/modules/Core/User/init';
import { registerInlineRenderer, registerTokenSource } from '@/modules/Messages/Chat/init';
import { RULE_PERMISSION_CATEGORY } from '@/modules/Roleplay/Rule/Constant/Permission/RULE_PERMISSION_CATEGORY';
import { KEYWORD_PERMISSION_CATEGORY } from '@/modules/Roleplay/Rule/Constant/Permission/KEYWORD_PERMISSION_CATEGORY';
import { KEYWORDS_ADMIN_SECTION } from '@/modules/Roleplay/Rule/Constant/Permission/KEYWORDS_ADMIN_SECTION';

export { ruleValidationService } from '@/modules/Roleplay/Rule/Service/Instance/ruleValidationService';
export { ruleDiffService } from '@/modules/Roleplay/Rule/Service/Instance/ruleDiffService';
export { mechanicEngine } from '@/modules/Roleplay/Rule/Service/Instance/mechanicEngine';
export type { ResolvedMechanic } from '@/modules/Roleplay/Rule/Dto/ResolvedMechanic';
export type { MechanicHandler } from '@/modules/Roleplay/Rule/Interface/MechanicHandler';
export { ROLL_EVENTS } from '@/modules/Roleplay/Rule/Constant/Mechanic/ROLL_EVENTS';
export { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/RULE_TYPE_LABELS';
export { itemModifierService } from '@/modules/Roleplay/Rule/Service/Instance/itemModifierService';
export { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';
export { checkLaunchService } from '@/modules/Roleplay/Rule/Service/Instance/checkLaunchService';
export { checkSuccessRatingService } from '@/modules/Roleplay/Rule/Service/Instance/checkSuccessRatingService';
export { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';
export { derivedCharacteristicService } from '@/modules/Roleplay/Rule/Service/Instance/derivedCharacteristicService';
export { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/Service/Instance/aggregateSourceDeltasService';
export { formatStateEffectsService } from '@/modules/Roleplay/Rule/Service/Instance/formatStateEffectsService';
export { raceSpecService } from '@/modules/Roleplay/Rule/Service/Instance/raceSpecService';
export { RaceSpecService } from '@/modules/Roleplay/Rule/Service/Spec/RaceSpecService';
export { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/Instance/ruleReferenceService';
export { PURCHASE_SURCHARGE_EVENT } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/PurchaseSurchargeHandler';
export { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_LABELS';
export type { ProblemEntry } from '@/modules/Roleplay/Rule/Dto/ProblemEntry';
export { ruleHostContextKey } from '@/modules/Roleplay/Rule/Constant/ruleHostContextKey';
export { useRuleHostContext } from '@/modules/Roleplay/Rule/Composables/useRuleHostContext';
export type { IRuleHostContext } from '@/modules/Roleplay/Rule/Interface/IRuleHostContext';
export type { IRevisionRulesFetcher } from '@/modules/Roleplay/Rule/Interface/IRevisionRulesFetcher';

/** Карточка способности для слайдера персонажа. Async — init не тянет Vue в node-тестах. */
export const AbilityCard = defineAsyncComponent(
  () => import('@/modules/Roleplay/Rule/Component/Cards/AbilityCard.vue'),
);

export function registerRuleApi(api: IRuleApi): void {
  serviceLocator.set('Roleplay.Rule.Service.RuleApi', api);
}

export function getRuleApi(): IRuleApi {
  return serviceLocator.get('Roleplay.Rule.Service.RuleApi');
}

export function registerKeywordApi(api: IKeywordApi): void {
  serviceLocator.set('Roleplay.Rule.Keyword.Service.KeywordApi', api);
}

export function getKeywordApi(): IKeywordApi {
  return serviceLocator.get('Roleplay.Rule.Keyword.Service.KeywordApi');
}

let revisionRulesFetcher: IRevisionRulesFetcher | null = null;

export function registerRevisionRulesFetcher(fetcher: IRevisionRulesFetcher): void {
  revisionRulesFetcher = fetcher;
}

export function getRevisionRulesFetcher(): IRevisionRulesFetcher | null {
  return revisionRulesFetcher;
}

export function registerRuleModule(): void {
  registerPermissionCategory(RULE_PERMISSION_CATEGORY);
  registerPermissionCategory(KEYWORD_PERMISSION_CATEGORY);
  registerAdminSection(KEYWORDS_ADMIN_SECTION);
  registerInlineRenderer({
    type: 'rule',
    component: defineAsyncComponent(() => import('@/modules/Roleplay/Rule/Component/RuleChip.vue')),
    describe: (segment) => {
      const override = segment.params[1]?.trim();
      if (override) return override;

      return segment.params[0] ?? '';
    },
  });
  registerTokenSource({
    type: 'rule',
    label: 'Правило',
    icon: 'mdi-book-open-variant',
    // Глобальный пикер без среза хоста: пусто. Поиск по ревизии — tokenSources провайдера.
    search: async () => [],
  });
}
