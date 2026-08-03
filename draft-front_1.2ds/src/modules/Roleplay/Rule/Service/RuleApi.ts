import type { Engine } from '@/modules/Core/Engine/Service/Engine'
import type { IRuleApi } from '@/modules/Roleplay/Rule/Interface/IRuleApi'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { CreateRuleData } from '@/modules/Roleplay/Rule/Dto/CreateRuleData'
import type { UpdateRuleData } from '@/modules/Roleplay/Rule/Dto/UpdateRuleData'
import type { RuleVersion } from '@/modules/Roleplay/Rule/Dto/RuleVersion'
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic'

export class RuleApi implements IRuleApi {
  constructor(private readonly engine: Engine) {}

  async getRules(spaceId: number, signal?: AbortSignal): Promise<Rule[]> {
    const res = await this.engine.runAction<Rule[]>('rule.getList', { spaceId }, signal)
    return res.data ?? []
  }

  async getRule(ruleId: string, signal?: AbortSignal): Promise<Rule> {
    const res = await this.engine.runAction<Rule>('rule.get', { ruleId }, signal)
    if (!res.data) throw new Error('Rule not found')
    return res.data
  }

  async getRuleVersions(ruleId: string, signal?: AbortSignal): Promise<RuleVersion[]> {
    const res = await this.engine.runAction<RuleVersion[]>('rule.getVersions', { ruleId }, signal)
    return res.data ?? []
  }

  async createRule(spaceId: number, data: CreateRuleData, signal?: AbortSignal): Promise<Rule> {
    const res = await this.engine.runAction<Rule>('rule.create', { spaceId, ...data }, signal)
    if (!res.data) throw new Error('Failed to create rule')
    return res.data
  }

  async updateRule(ruleId: string, data: UpdateRuleData, signal?: AbortSignal): Promise<Rule> {
    const res = await this.engine.runAction<Rule>('rule.update', { ruleId, ...data }, signal)
    if (!res.data) throw new Error('Failed to update rule')
    return res.data
  }

  async deleteRule(ruleId: string, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('rule.delete', { ruleId }, signal)
  }

  async getMechanics(signal?: AbortSignal): Promise<Mechanic[]> {
    const res = await this.engine.runAction<Mechanic[]>('rule.getMechanics', {}, signal)
    return res.data ?? []
  }
}
