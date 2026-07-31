import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Rule } from '../Interface/types'

interface DraftEntry {
  spaceId: number
  changedRules: Record<string, Rule>
}

export const useDraftRuleStore = defineStore('draftRules', () => {
  const drafts = ref<DraftEntry[]>([])

  const activeDraft = computed(() => (spaceId: number): DraftEntry | undefined => {
    return drafts.value.find(d => d.spaceId === spaceId)
  })

  function hasDraft(spaceId: number): boolean {
    const entry = drafts.value.find(d => d.spaceId === spaceId)
    return !!entry && Object.keys(entry.changedRules).length > 0
  }

  function ensureDraft(spaceId: number): DraftEntry {
    let entry = drafts.value.find(d => d.spaceId === spaceId)
    if (!entry) {
      entry = { spaceId, changedRules: {} }
      drafts.value.push(entry)
    }
    return entry
  }

  function saveRule(spaceId: number, rule: Rule): void {
    const entry = ensureDraft(spaceId)
    entry.changedRules = { ...entry.changedRules, [rule.id]: rule }
  }

  function removeRule(spaceId: number, ruleId: string): void {
    const entry = drafts.value.find(d => d.spaceId === spaceId)
    if (entry) {
      const { [ruleId]: _, ...rest } = entry.changedRules
      entry.changedRules = rest
    }
  }

  function getDraftRules(spaceId: number): Rule[] {
    const entry = drafts.value.find(d => d.spaceId === spaceId)
    return entry ? Object.values(entry.changedRules) : []
  }

  function addToDraft(spaceId: number, rule: Rule): boolean {
    const entry = ensureDraft(spaceId)
    const existed = rule.id in entry.changedRules
    entry.changedRules = { ...entry.changedRules, [rule.id]: rule }
    return existed
  }

  function discardDraft(spaceId: number): void {
    const idx = drafts.value.findIndex(d => d.spaceId === spaceId)
    if (idx !== -1) drafts.value.splice(idx, 1)
  }

  function clearAll(): void {
    drafts.value = []
  }

  return {
    drafts,
    activeDraft,
    hasDraft,
    saveRule,
    removeRule,
    getDraftRules,
    addToDraft,
    discardDraft,
    clearAll,
  }
})
