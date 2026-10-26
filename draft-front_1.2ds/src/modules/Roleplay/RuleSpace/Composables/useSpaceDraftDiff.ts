import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { ruleDiffService, useRuleDrafts } from '@/modules/Roleplay/Rule/init';
import type { PublishDiff } from '@/modules/Roleplay/Rule/Dto/PublishDiff';
import { useSpaceRevisionStore } from '@/modules/Roleplay/RuleSpace/Store/spaceRevision';

const EMPTY_DIFF: PublishDiff = { added: [], changed: [], removed: [] };

/**
 * Единый дифф черновика пространства относительно активной опубликованной ревизии.
 * Список правил и диалог публикации должны опираться на него, а не на факт записи в localStorage.
 */
export function useSpaceDraftDiff(spaceId: MaybeRefOrGetter<number | null | undefined>) {
  const revisionStore = useSpaceRevisionStore();
  const drafts = useRuleDrafts();

  const draftDiff = computed<PublishDiff>(() => {
    const id = toValue(spaceId);
    if (!id) return EMPTY_DIFF;

    return ruleDiffService.classifyDraftDiff(
      revisionStore.activeRevision?.rules ?? [],
      drafts.getDraftRules(id),
      drafts.getRemovedCodes(id),
    );
  });

  const addedRuleCodes = computed(() => new Set(draftDiff.value.added.map((rule) => rule.code)));
  const changedRuleCodes = computed(() => new Set(draftDiff.value.changed.map((rule) => rule.code)));
  const affectedRuleCodes = computed(() => new Set([...addedRuleCodes.value, ...changedRuleCodes.value]));
  const hasRuleChanges = computed(
    () => draftDiff.value.added.length > 0 || draftDiff.value.changed.length > 0 || draftDiff.value.removed.length > 0,
  );

  return {
    draftDiff,
    addedRuleCodes,
    changedRuleCodes,
    affectedRuleCodes,
    hasRuleChanges,
  };
}
