import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import { sectionDraftPersistService } from '@/modules/Roleplay/RuleSpace/Service/Instance/sectionDraftPersistService';
import { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/Instance/abilitySectionTreeService';

export const useSectionCatalogStore = defineStore('sectionCatalog', () => {
  const loaded = sectionDraftPersistService.read();
  const drafts = ref<Map<number, AbilitySection[]>>(
    new Map(Object.entries(loaded.bySpaceId).map(([key, sections]) => [Number(key), sections])),
  );
  const storageDiscarded = ref(loaded.discarded);

  function persist(): void {
    const bySpaceId: Record<string, AbilitySection[]> = {};
    for (const [spaceId, sections] of drafts.value) {
      bySpaceId[String(spaceId)] = sections;
    }
    sectionDraftPersistService.write(bySpaceId);
  }

  function getDraftSections(spaceId: number): AbilitySection[] | null {
    return drafts.value.get(spaceId) ?? null;
  }

  function saveDraft(spaceId: number, sections: AbilitySection[]): void {
    drafts.value.set(spaceId, abilitySectionTreeService.normalize(sections));
    persist();
  }

  function discardDraft(spaceId: number): void {
    drafts.value.delete(spaceId);
    persist();
  }

  function isDirty(spaceId: number, published: AbilitySection[]): boolean {
    const draft = drafts.value.get(spaceId);
    if (!draft) return false;

    return !abilitySectionTreeService.sameCatalog(draft, published);
  }

  function acknowledgeStorageDiscarded(): void {
    storageDiscarded.value = false;
  }

  return {
    storageDiscarded,
    getDraftSections,
    saveDraft,
    discardDraft,
    isDirty,
    acknowledgeStorageDiscarded,
  };
});
