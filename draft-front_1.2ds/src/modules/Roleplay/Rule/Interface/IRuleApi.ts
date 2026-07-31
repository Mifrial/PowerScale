import type { Rule, CreateRuleData, UpdateRuleData, RuleVersion } from './types'

export interface IRuleApi {
  getRules(spaceId: number, signal?: AbortSignal): Promise<Rule[]>
  getRule(ruleId: string, signal?: AbortSignal): Promise<Rule>
  getRuleVersions(ruleId: string, signal?: AbortSignal): Promise<RuleVersion[]>
  createRule(spaceId: number, data: CreateRuleData, signal?: AbortSignal): Promise<Rule>
  updateRule(ruleId: string, data: UpdateRuleData, signal?: AbortSignal): Promise<Rule>
  deleteRule(ruleId: string, signal?: AbortSignal): Promise<void>
}
