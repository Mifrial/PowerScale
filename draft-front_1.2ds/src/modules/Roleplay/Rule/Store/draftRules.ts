import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { DraftEntry } from '@/modules/Roleplay/Rule/Dto/DraftEntry';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { draftRulesPersistService } from '@/modules/Roleplay/Rule/Service/Instance/draftRulesPersistService';

export const useDraftRuleStore = defineStore('draftRules', () => {
  const loaded = draftRulesPersistService.read();
  const storageDiscarded = ref(loaded.discarded);
  const drafts = ref<DraftEntry[]>(loaded.entries);

  function persistDrafts(): void {
    draftRulesPersistService.write(drafts.value);
  }

  function acknowledgeStorageDiscarded(): void {
    storageDiscarded.value = false;
  }

  const activeDraft = computed(() => (spaceId: number): DraftEntry | undefined => {
    return drafts.value.find((d) => d.spaceId === spaceId);
  });

  function hasDraft(spaceId: number): boolean {
    const entry = drafts.value.find((d) => d.spaceId === spaceId);

    return !!entry && Object.keys(entry.changedRules).length > 0;
  }

  function ensureDraft(spaceId: number): DraftEntry {
    let entry = drafts.value.find((d) => d.spaceId === spaceId);
    if (!entry) {
      entry = { spaceId, changedRules: {} };
      drafts.value.push(entry);
    }

    return entry;
  }

  function saveRule(spaceId: number, rule: Rule): void {
    const entry = ensureDraft(spaceId);
    entry.changedRules = { ...entry.changedRules, [rule.id]: rule };
    persistDrafts();
  }

  function removeRule(spaceId: number, ruleId: string): void {
    const entry = drafts.value.find((d) => d.spaceId === spaceId);
    if (entry) {
      const { [ruleId]: _, ...rest } = entry.changedRules;
      entry.changedRules = rest;
      persistDrafts();
    }
  }

  function getDraftRules(spaceId: number): Rule[] {
    const entry = drafts.value.find((d) => d.spaceId === spaceId);

    return entry ? Object.values(entry.changedRules) : [];
  }

  function addToDraft(spaceId: number, rule: Rule): boolean {
    const entry = ensureDraft(spaceId);
    const existed = rule.id in entry.changedRules;
    entry.changedRules = { ...entry.changedRules, [rule.id]: rule };
    persistDrafts();

    return existed;
  }

  function discardDraft(spaceId: number): void {
    const idx = drafts.value.findIndex((d) => d.spaceId === spaceId);
    if (idx !== -1) drafts.value.splice(idx, 1);
    persistDrafts();
  }

  function clearAll(): void {
    drafts.value = [];
    persistDrafts();
  }

  return {
    drafts,
    storageDiscarded,
    acknowledgeStorageDiscarded,
    activeDraft,
    hasDraft,
    saveRule,
    removeRule,
    getDraftRules,
    addToDraft,
    discardDraft,
    clearAll,
  };
});
