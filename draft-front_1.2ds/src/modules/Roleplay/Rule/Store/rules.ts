import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleVersion } from '@/modules/Roleplay/Rule/Dto/RuleVersion';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';

export const useRuleStore = defineStore('rules', () => {
  const currentRule = ref<Rule | null>(null);
  const ruleVersions = ref<RuleVersion[]>([]);

  async function fetchRule(ruleId: string, signal?: AbortSignal): Promise<Rule> {
    const rule = await getRuleApi().getRule(ruleId, signal);
    currentRule.value = rule;

    return rule;
  }

  async function fetchRuleVersions(ruleId: string, signal?: AbortSignal) {
    ruleVersions.value = await getRuleApi().getRuleVersions(ruleId, signal);
  }

  function setCurrentRule(rule: Rule) {
    currentRule.value = rule;
  }

  function clearCurrent() {
    currentRule.value = null;
    ruleVersions.value = [];
  }

  return {
    currentRule,
    ruleVersions,
    fetchRule,
    fetchRuleVersions,
    setCurrentRule,
    clearCurrent,
  };
});
