import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { defineAsyncComponent } from 'vue';
import type { IRuleApi } from '@/modules/Roleplay/Rule/Interface/IRuleApi';
import type { IKeywordApi } from '@/modules/Roleplay/Rule/Interface/IKeywordApi';
import { registerPermissionCategory, registerAdminSection } from '@/modules/Core/User/init';
import { registerInlineRenderer, registerTokenSource } from '@/modules/Messages/Chat/init';
import { RULE_PERMISSION_CATEGORY } from '@/modules/Roleplay/Rule/Constant/Permission/RULE_PERMISSION_CATEGORY';
import { KEYWORD_PERMISSION_CATEGORY } from '@/modules/Roleplay/Rule/Constant/Permission/KEYWORD_PERMISSION_CATEGORY';
import { KEYWORDS_ADMIN_SECTION } from '@/modules/Roleplay/Rule/Constant/Permission/KEYWORDS_ADMIN_SECTION';
import { useRuleCatalogStore } from '@/modules/Roleplay/Rule/Store/ruleCatalog';

export { ruleValidationService } from '@/modules/Roleplay/Rule/Service/Instance/ruleValidationService';
export { ruleDiffService } from '@/modules/Roleplay/Rule/Service/Instance/ruleDiffService';
export { mechanicEngine } from '@/modules/Roleplay/Rule/Service/Instance/mechanicEngine';
export type { ResolvedMechanic } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicEngine';
export type { MechanicHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicHandler';
export { ROLL_EVENTS } from '@/modules/Roleplay/Rule/Constant/Mechanic/ROLL_EVENTS';
export { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/RULE_TYPE_LABELS';
export { itemModifierService } from '@/modules/Roleplay/Rule/Service/Instance/itemModifierService';
export { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_LABELS';
export type { ProblemEntry } from '@/modules/Roleplay/Rule/Dto/ProblemEntry';

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

export function registerRuleModule(): void {
  registerPermissionCategory(RULE_PERMISSION_CATEGORY);
  registerPermissionCategory(KEYWORD_PERMISSION_CATEGORY);
  registerAdminSection(KEYWORDS_ADMIN_SECTION);
  useRuleCatalogStore()
    .ensureLoaded()
    .catch(() => {
      // каталог подгрузится при открытии чипа правила
    });
  registerInlineRenderer({
    type: 'rule',
    component: defineAsyncComponent(() => import('@/modules/Roleplay/Rule/Component/RuleChip.vue')),
    describe: (segment) => {
      const override = segment.params[1]?.trim();
      if (override) return override;
      const code = segment.params[0] ?? '';
      const rule = useRuleCatalogStore().findRule(code);

      return rule?.name ?? code;
    },
  });
  registerTokenSource({
    type: 'rule',
    label: 'Правило',
    icon: 'mdi-book-open-variant',
    search: async (query) => {
      const rules = await getRuleApi().getRules(0);
      const q = query.toLowerCase();
      const matched = q
        ? rules.filter((r) => r.name.toLowerCase().includes(q) || r.code.toLowerCase().includes(q))
        : rules.slice(0, 10);

      return matched.map((r) => ({ value: r.code, label: r.name }));
    },
  });
}
