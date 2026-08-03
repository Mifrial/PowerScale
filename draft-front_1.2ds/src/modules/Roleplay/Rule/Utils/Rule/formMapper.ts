import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { RuleFormState } from '@/modules/Roleplay/Rule/Dto/RuleFormState'

export function ruleToForm(rule: Rule): RuleFormState {
  return {
    type: rule.type,
    name: rule.name,
    code: rule.code,
    loadedCode: rule.code,
    description: rule.description,
    mechanicId: rule.mechanicId ?? null,
    keywordIds: rule.keywordIds ?? [],
    spec: rule.spec ?? null,
  }
}
