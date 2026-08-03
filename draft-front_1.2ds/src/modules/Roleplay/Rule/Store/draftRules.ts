import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import { DRAFT_RULES_STORAGE_KEY } from '@/modules/Roleplay/Rule/Constant/draftRulesConfig'

interface DraftEntry {
  spaceId: number
  changedRules: Record<string, Rule>
}

function loadDrafts(): DraftEntry[] {
  try {
    const raw = localStorage.getItem(DRAFT_RULES_STORAGE_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []
    return parsed.filter((d): d is DraftEntry =>
      typeof d === 'object' && d !== null && typeof d.spaceId === 'number' && typeof d.changedRules === 'object',
    )
  } catch {
    return []
  }
}

function persistDrafts(drafts: DraftEntry[]): void {
  try {
    if (drafts.length === 0) {
      localStorage.removeItem(DRAFT_RULES_STORAGE_KEY)
      return
    }
    localStorage.setItem(DRAFT_RULES_STORAGE_KEY, JSON.stringify(drafts))
  } catch {
    // localStorage недоступен (квота/режим) — черновик остаётся in-memory
  }
}

export const useDraftRuleStore = defineStore('draftRules', () => {
  const drafts = ref<DraftEntry[]>(loadDrafts())

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
    persistDrafts(drafts.value)
  }

  function removeRule(spaceId: number, ruleId: string): void {
    const entry = drafts.value.find(d => d.spaceId === spaceId)
    if (entry) {
      const { [ruleId]: _, ...rest } = entry.changedRules
      entry.changedRules = rest
      persistDrafts(drafts.value)
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
    persistDrafts(drafts.value)
    return existed
  }

  function discardDraft(spaceId: number): void {
    const idx = drafts.value.findIndex(d => d.spaceId === spaceId)
    if (idx !== -1) drafts.value.splice(idx, 1)
    persistDrafts(drafts.value)
  }

  function clearAll(): void {
    drafts.value = []
    persistDrafts(drafts.value)
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
