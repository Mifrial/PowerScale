import { sl } from '@/modules/Core/Engine/ServiceLocator'
import type { IRuleApi } from '@/modules/Roleplay/Rule/Interface/IRuleApi'

export function registerRuleApi(api: IRuleApi): void {
  sl.set('Roleplay.Rule.Service.RuleApi', api)
}

export function getRuleApi(): IRuleApi {
  return sl.get('Roleplay.Rule.Service.RuleApi')
}
