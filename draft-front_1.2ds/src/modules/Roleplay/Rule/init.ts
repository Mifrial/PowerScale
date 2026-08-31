import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { defineAsyncComponent } from 'vue';
import type { IRuleApi } from '@/modules/Roleplay/Rule/Interface/IRuleApi';
import type { IKeywordApi } from '@/modules/Roleplay/Rule/Interface/IKeywordApi';
import type { IRevisionRulesFetcher } from '@/modules/Roleplay/Rule/Interface/IRevisionRulesFetcher';
import { revisionRulesFetcherRegistry } from '@/modules/Roleplay/Rule/Service/Instance/revisionRulesFetcherRegistry';
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
export { actionEffectLabelService } from '@/modules/Roleplay/Rule/Service/Instance/actionEffectLabelService';
export { movementDistanceExpressionService } from '@/modules/Roleplay/Rule/Service/Instance/movementDistanceExpressionService';
export { MechanicEngine } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicEngine';
export { MechanicHandlerRegistry } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicHandlerRegistry';
export { rollAdvantageHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/RollAdvantageHandler';
export { rollSixOneHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/RollSixOneHandler';
export { rollCriticalStrikeHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/RollCriticalStrikeHandler';
export { CharacteristicNumber, CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
export { parameterLimitName } from '@/modules/Roleplay/Rule/Utils/parameterLimitName';
export { resourceShortName } from '@/modules/Roleplay/Rule/Utils/resourceShortName';
export { slugify } from '@/modules/Roleplay/Rule/Utils/Text/slugify';
export * from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
export * from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
export { HIT_MIN_SUCCESS_SIZE } from '@/modules/Roleplay/Rule/Constant/Check/HIT_MIN_SUCCESS_SIZE';
export * from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
export * from '@/modules/Roleplay/Rule/Constant/Damage/DAMAGE_TYPE_HOOKS';
export * from '@/modules/Roleplay/Rule/Constant/Combat/HIT_PROCEDURE';
export * from '@/modules/Roleplay/Rule/Constant/Combat/INJURY_PROCEDURE';
export { PURCHASE_SURCHARGE_EVENT } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/PurchaseSurchargeHandler';
export { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_LABELS';
export type { ProblemEntry } from '@/modules/Roleplay/Rule/Dto/ProblemEntry';
export { ruleHostContextKey } from '@/modules/Roleplay/Rule/Constant/ruleHostContextKey';
export { useRuleHostContext } from '@/modules/Roleplay/Rule/Composables/useRuleHostContext';
export { useKeywords } from '@/modules/Roleplay/Rule/Composables/useKeywords';
export { useRuleDrafts } from '@/modules/Roleplay/Rule/Composables/useRuleDrafts';
export { DOMAIN_REF_RULE_TYPES } from '@/modules/Roleplay/Rule/Constant/Ability/DOMAIN_REF_RULE_TYPES';
export { DOMAIN_STATIC_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/DOMAIN_STATIC_OPTIONS';
export { ACTION_POINTS_RESOURCE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/ACTION_POINTS_RESOURCE_CODE';
export { DAMAGE_TYPE_FORMS } from '@/modules/Roleplay/Rule/Constant/DAMAGE_TYPE_FORMS';
export type { IRuleHostContext } from '@/modules/Roleplay/Rule/Interface/IRuleHostContext';
export type { IRevisionRulesFetcher } from '@/modules/Roleplay/Rule/Interface/IRevisionRulesFetcher';

/** Карточка способности для слайдера персонажа. Async — init не тянет Vue в node-тестах. */
export const AbilityCard = defineAsyncComponent(
  () => import('@/modules/Roleplay/Rule/Component/Cards/AbilityCard.vue'),
);

export const RuleSlider = defineAsyncComponent(() => import('@/modules/Roleplay/Rule/Component/RuleSlider.vue'));

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

export function registerRevisionRulesFetcher(fetcher: IRevisionRulesFetcher): void {
  revisionRulesFetcherRegistry.register(fetcher);
}

export function getRevisionRulesFetcher(): IRevisionRulesFetcher | null {
  return revisionRulesFetcherRegistry.get();
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
