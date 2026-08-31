import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { useDraftRuleStore } from '@/modules/Roleplay/Rule/Store/draftRules';

export function useRuleDrafts() {
  const store = useDraftRuleStore();

  return {
    storageDiscarded: computed(() => store.storageDiscarded),
    acknowledgeStorageDiscarded: () => store.acknowledgeStorageDiscarded(),
    hasDraft: (spaceId: number) => store.hasDraft(spaceId),
    saveRule: (spaceId: number, rule: Rule) => store.saveRule(spaceId, rule),
    saveRules: (spaceId: number, rules: Rule[]) => store.saveRules(spaceId, rules),
    removeRule: (spaceId: number, code: string) => store.removeRule(spaceId, code),
    getDraftRules: (spaceId: number) => store.getDraftRules(spaceId),
    getRemovedCodes: (spaceId: number) => store.getRemovedCodes(spaceId),
    setRemovedCodes: (spaceId: number, codes: string[]) => store.setRemovedCodes(spaceId, codes),
    discardDraft: (spaceId: number) => store.discardDraft(spaceId),
  };
}
