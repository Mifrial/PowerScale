import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { accessService, useCurrentUser } from '@/modules/Core/User/init';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { AbilitySectionMutation } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySectionMutation';
import type { AbilitySectionPatch } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySectionPatch';
import type { SectionDropPlacement } from '@/modules/Roleplay/RuleSpace/Enum/SectionDropPlacement';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/Instance/abilitySectionTreeService';
import { useSectionCatalogStore } from '@/modules/Roleplay/RuleSpace/Store/sectionCatalog';
import { useSpaceRevisionStore } from '@/modules/Roleplay/RuleSpace/Store/spaceRevision';
import { useSpaceStore } from '@/modules/Roleplay/RuleSpace/Store/spaces';

/**
 * Дерево секций выбранного контекста пространства: черновик на `/draft/sections`, снимок на `/:revision/sections`.
 */
export function useSectionCatalogEdit() {
  const spaceStore = useSpaceStore();
  const revisionStore = useSpaceRevisionStore();
  const sectionCatalog = useSectionCatalogStore();
  const { currentUser } = useCurrentUser();
  const { signal } = useAbortable();
  const route = useRoute();

  const sections = ref<AbilitySection[]>([]);
  const published = ref<AbilitySection[]>([]);
  const loading = ref(false);
  const loadError = ref<string | null>(null);
  const mutationErrors = ref<string[]>([]);

  const space = computed(() => spaceStore.currentSpace);
  const isDraftContext = computed(() => route.params.ctx === 'draft');
  const latestPublishedRevision = computed(() => {
    const current = space.value;
    if (!current) return 0;
    const meta = revisionStore.revisionsMeta.get(current.id) ?? [];
    if (meta.length > 0) return meta[meta.length - 1].revision;

    return current.revision;
  });
  const viewingRevision = computed(() => revisionStore.activeRevision?.revision ?? 0);
  const isAgainstLatest = computed(
    () => isDraftContext.value && viewingRevision.value === latestPublishedRevision.value,
  );
  const canManageCatalog = computed(() => {
    const current = space.value;
    if (!current) return false;

    return (
      current.ownerId === currentUser.value?.id || accessService.hasAnyPermission(currentUser.value, ['space.edit_all'])
    );
  });
  const canEdit = computed(() => canManageCatalog.value && isAgainstLatest.value);
  const isDirty = computed(() => {
    const current = space.value;
    if (!current || !isAgainstLatest.value) return false;

    return sectionCatalog.isDirty(current.id, published.value);
  });

  async function load(): Promise<void> {
    const current = space.value;
    if (!current) return;
    loading.value = true;
    loadError.value = null;
    mutationErrors.value = [];
    try {
      if (isDraftContext.value) {
        const latest = await revisionStore.resolveLatestRevision(current.id, signal.value);
        await revisionStore.syncFromContext(current.id, 'draft', latest, signal.value);
        published.value = cloneData(revisionStore.activeRevision?.sections ?? []);
        const draft = sectionCatalog.getDraftSections(current.id);
        sections.value = cloneData(draft ?? published.value);
      } else {
        published.value = cloneData(revisionStore.activeRevision?.sections ?? []);
        sections.value = cloneData(published.value);
      }
      if (sectionCatalog.storageDiscarded) {
        loadError.value = 'Черновик секций в браузере повреждён и сброшен';
        sectionCatalog.acknowledgeStorageDiscarded();
      }
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') return;
      loadError.value = error instanceof Error ? error.message : 'Не удалось загрузить секции';
    } finally {
      loading.value = false;
    }
  }

  function apply(mutation: AbilitySectionMutation): boolean {
    if (!canEdit.value) return false;
    mutationErrors.value = mutation.errors;
    if (mutation.errors.length > 0) return false;
    const current = space.value;
    if (!current) return false;
    const rules = revisionStore.effectiveRules;
    const ruleErrors = abilitySectionTreeService.validateRuleSections(rules, mutation.sections);
    if (ruleErrors.length > 0) {
      mutationErrors.value = ruleErrors;

      return false;
    }
    sections.value = mutation.sections;
    if (abilitySectionTreeService.sameCatalog(mutation.sections, published.value)) {
      sectionCatalog.discardDraft(current.id);
    } else {
      sectionCatalog.saveDraft(current.id, mutation.sections);
    }

    return true;
  }

  function addSection(section: AbilitySection): boolean {
    return apply(abilitySectionTreeService.addSection(sections.value, section));
  }

  function updateSection(code: string, patch: AbilitySectionPatch): boolean {
    return apply(abilitySectionTreeService.updateSection(sections.value, code, patch));
  }

  function placeSection(code: string, parentCode: string | null, siblingIndex: number): boolean {
    return apply(abilitySectionTreeService.placeSection(sections.value, code, parentCode, siblingIndex));
  }

  function dropSection(sourceCode: string, targetCode: string, placement: SectionDropPlacement): boolean {
    return apply(abilitySectionTreeService.dropSection(sections.value, sourceCode, targetCode, placement));
  }

  function removeSection(code: string): boolean {
    return apply(abilitySectionTreeService.removeSection(sections.value, code, revisionStore.effectiveRules));
  }

  function removeBlockReason(code: string): string | null {
    return (
      abilitySectionTreeService.removeSection(sections.value, code, revisionStore.effectiveRules).errors[0] ?? null
    );
  }

  function reset(): void {
    const current = space.value;
    if (!current || !canEdit.value) return;
    sectionCatalog.discardDraft(current.id);
    sections.value = cloneData(published.value);
    mutationErrors.value = [];
  }

  return {
    space,
    sections,
    published,
    loading,
    loadError,
    mutationErrors,
    canEdit,
    canManageCatalog,
    isDirty,
    isDraftContext,
    isAgainstLatest,
    viewingRevision,
    latestPublishedRevision,
    load,
    addSection,
    updateSection,
    placeSection,
    dropSection,
    removeSection,
    removeBlockReason,
    reset,
  };
}
