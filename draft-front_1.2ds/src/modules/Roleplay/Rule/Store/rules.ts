import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Rule } from '../Dto/Rule'
import type { CreateRuleData } from '../Dto/CreateRuleData'
import type { UpdateRuleData } from '../Dto/UpdateRuleData'
import type { RuleVersion } from '../Dto/RuleVersion'
import { getRuleApi } from '../init'

export const useRuleStore = defineStore('rules', () => {
  const currentRule = ref<Rule | null>(null)
  const ruleVersions = ref<RuleVersion[]>([])
  const loading = ref(false)

  async function fetchRule(ruleId: string, signal?: AbortSignal): Promise<Rule> {
    loading.value = true
    try {
      const rule = await getRuleApi().getRule(ruleId, signal)
      currentRule.value = rule
      return rule
    } finally {
      loading.value = false
    }
  }

  async function fetchRuleVersions(ruleId: string, signal?: AbortSignal) {
    ruleVersions.value = await getRuleApi().getRuleVersions(ruleId, signal)
  }

  async function createRule(spaceId: number, data: CreateRuleData, signal?: AbortSignal): Promise<Rule> {
    return await getRuleApi().createRule(spaceId, data, signal)
  }

  async function updateRule(ruleId: string, data: UpdateRuleData, signal?: AbortSignal): Promise<Rule> {
    const rule = await getRuleApi().updateRule(ruleId, data, signal)
    if (currentRule.value?.id === ruleId) currentRule.value = rule
    return rule
  }

  async function deleteRule(ruleId: string, signal?: AbortSignal): Promise<void> {
    await getRuleApi().deleteRule(ruleId, signal)
    if (currentRule.value?.id === ruleId) currentRule.value = null
  }

  function clearCurrent() {
    currentRule.value = null
    ruleVersions.value = []
  }

  return {
    currentRule, ruleVersions, loading,
    fetchRule, fetchRuleVersions, createRule, updateRule, deleteRule, clearCurrent,
  }
})
