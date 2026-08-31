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

    return !!entry && (Object.keys(entry.changedRules).length > 0 || (entry.removedCodes?.length ?? 0) > 0);
  }

  function ensureDraft(spaceId: number): DraftEntry {
    let entry = drafts.value.find((d) => d.spaceId === spaceId);
    if (!entry) {
      entry = { spaceId, changedRules: {}, removedCodes: [] };
      drafts.value.push(entry);
    }
    if (!entry.removedCodes) entry.removedCodes = [];

    return entry;
  }

  function saveRules(spaceId: number, rules: Rule[]): void {
    if (rules.length === 0) return;
    const entry = ensureDraft(spaceId);
    const next = { ...entry.changedRules };
    const importedCodes = new Set<string>();
    for (const rule of rules) {
      next[rule.code] = rule;
      importedCodes.add(rule.code);
    }
    entry.changedRules = next;
    entry.removedCodes = entry.removedCodes.filter((code) => !importedCodes.has(code));
    persistDrafts();
  }

  function saveRule(spaceId: number, rule: Rule): void {
    saveRules(spaceId, [rule]);
  }

  function removeRule(spaceId: number, code: string): void {
    const entry = drafts.value.find((d) => d.spaceId === spaceId);
    if (entry) {
      const { [code]: _, ...rest } = entry.changedRules;
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
    const existed = rule.code in entry.changedRules;
    entry.changedRules = { ...entry.changedRules, [rule.code]: rule };
    entry.removedCodes = entry.removedCodes.filter((code) => code !== rule.code);
    persistDrafts();

    return existed;
  }

  function getRemovedCodes(spaceId: number): string[] {
    return drafts.value.find((d) => d.spaceId === spaceId)?.removedCodes ?? [];
  }

  function setRemovedCodes(spaceId: number, codes: string[]): void {
    const entry = ensureDraft(spaceId);
    const unique = [...new Set(codes)];
    entry.removedCodes = unique;
    const drop = new Set(unique);
    entry.changedRules = Object.fromEntries(
      Object.entries(entry.changedRules).filter(([, rule]) => !drop.has(rule.code)),
    );
    persistDrafts();
    if (Object.keys(entry.changedRules).length === 0 && entry.removedCodes.length === 0) {
      discardDraft(spaceId);
    }
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
    saveRules,
    removeRule,
    getDraftRules,
    addToDraft,
    getRemovedCodes,
    setRemovedCodes,
    discardDraft,
    clearAll,
  };
});
