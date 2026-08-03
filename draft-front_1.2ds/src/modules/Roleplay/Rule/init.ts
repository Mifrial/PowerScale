import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { IRuleApi } from '@/modules/Roleplay/Rule/Interface/IRuleApi';
import type { IKeywordApi } from '@/modules/Roleplay/Rule/Interface/IKeywordApi';
import { registerPermissionCategory, registerAdminSection } from '@/modules/Core/User/init';
import {
  RULE_PERMISSION_CATEGORY,
  KEYWORD_PERMISSION_CATEGORY,
  KEYWORDS_ADMIN_SECTION,
} from '@/modules/Roleplay/Rule/Constant/permissions';

export { ruleValidationService } from '@/modules/Roleplay/Rule/Service/RuleValidationService';
export { ruleDiffService } from '@/modules/Roleplay/Rule/Service/RuleDiffService';
export type { ProblemEntry } from '@/modules/Roleplay/Rule/Service/RuleDiffService';

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
}
