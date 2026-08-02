import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { CreateRuleData } from '@/modules/Roleplay/Rule/Dto/CreateRuleData'
import type { UpdateRuleData } from '@/modules/Roleplay/Rule/Dto/UpdateRuleData'
import type { RuleVersion } from '@/modules/Roleplay/Rule/Dto/RuleVersion'
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic'

export interface IRuleApi {
  getRules(spaceId: number, signal?: AbortSignal): Promise<Rule[]>
  getRule(ruleId: string, signal?: AbortSignal): Promise<Rule>
  getRuleVersions(ruleId: string, signal?: AbortSignal): Promise<RuleVersion[]>
  createRule(spaceId: number, data: CreateRuleData, signal?: AbortSignal): Promise<Rule>
  updateRule(ruleId: string, data: UpdateRuleData, signal?: AbortSignal): Promise<Rule>
  deleteRule(ruleId: string, signal?: AbortSignal): Promise<void>
  getMechanics(signal?: AbortSignal): Promise<Mechanic[]>
}
