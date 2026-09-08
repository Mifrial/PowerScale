import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { SpaceRevisionMeta } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevisionMeta';
import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';
import type { RevisionKind } from '@/modules/Roleplay/RuleSpace/Enum/RevisionKind';
import type { RevisionContext } from '@/modules/Roleplay/RuleSpace/Dto/RevisionContext';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { getRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/init';
import { useRuleDrafts } from '@/modules/Roleplay/Rule/init';
import { useSectionCatalogStore } from '@/modules/Roleplay/RuleSpace/Store/sectionCatalog';
import { useSpaceStore } from '@/modules/Roleplay/RuleSpace/Store/spaces';

export const useSpaceRevisionStore = defineStore('spaceRevision', () => {
  const drafts = useRuleDrafts();
  const sectionCatalog = useSectionCatalogStore();
  const spaceStore = useSpaceStore();
  const revisionsMeta = ref<Map<number, SpaceRevisionMeta[]>>(new Map());
  const cachedRevisions = ref<Map<string, SpaceRevision<Rule>>>(new Map());

  const activeContext = ref<RevisionContext>({ spaceId: null, revision: null, kind: 'rev' });

  const activeRevision = computed<SpaceRevision<Rule> | null>(() => {
    if (!activeContext.value.spaceId || activeContext.value.revision === null) return null;

    return cachedRevisions.value.get(cacheKey(activeContext.value.spaceId, activeContext.value.revision)) ?? null;
  });

  const effectiveRules = computed<Rule[]>(() => {
    const ctx = activeContext.value;
    if (!ctx.spaceId) return [];
    const revision = activeRevision.value;
    const published = revision?.rules ?? [];

    if (ctx.kind === 'rev') return published;

    const draftRules = drafts.getDraftRules(ctx.spaceId);
    const removedCodes = new Set(drafts.getRemovedCodes(ctx.spaceId));
    if (draftRules.length === 0 && removedCodes.size === 0) return published;

    const draftMap = new Map(draftRules.map((r) => [r.code, r]));
    const merged = published.filter((r) => !removedCodes.has(r.code)).map((r) => draftMap.get(r.code) ?? r);
    const newRules = draftRules.filter((r) => !published.some((p) => p.code === r.code));

    return [...merged, ...newRules];
  });

  const effectiveSections = computed<AbilitySection[]>(() => {
    const published = activeRevision.value?.sections ?? [];
    const spaceId = activeContext.value.spaceId;
    if (!spaceId || activeContext.value.kind !== 'draft') return published;
    const draft = sectionCatalog.getDraftSections(spaceId);

    return draft ?? published;
  });

  function cacheKey(spaceId: number, revision: number): string {
    return `${spaceId}:${revision}`;
  }

  async function fetchRevisionsMeta(spaceId: number, signal?: AbortSignal): Promise<SpaceRevisionMeta[]> {
    const api = getRuleSpaceApi();
    const meta = await api.getRevisions(spaceId, signal);
    revisionsMeta.value.set(spaceId, meta);

    return meta;
  }

  function unpublishedSlice(spaceId: number): SpaceRevision<Rule> {
    const spaces = useSpaceStore();
    const space =
      spaces.currentSpace?.id === spaceId ? spaces.currentSpace : spaces.spaces.find((row) => row.id === spaceId);

    return {
      revision: 0,
      publishedAt: space?.createdAt ?? 0,
      spaceCode: space?.code ?? '',
      spaceName: space?.name ?? '',
      rules: [],
      sections: [],
    };
  }

  async function fetchRevision(spaceId: number, revision: number, signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
    const key = cacheKey(spaceId, revision);
    const cached = cachedRevisions.value.get(key);
    if (cached) return cached;

    if (revision < 1) {
      const empty = unpublishedSlice(spaceId);
      cachedRevisions.value.set(key, empty);

      return empty;
    }

    const rev = await getRuleSpaceApi().getRevision(spaceId, revision, signal);
    cachedRevisions.value.set(key, rev);

    return rev;
  }

  async function resolveLatestRevision(spaceId: number, signal?: AbortSignal): Promise<number> {
    const meta = revisionsMeta.value.get(spaceId);
    if (meta && meta.length > 0) return meta[meta.length - 1].revision;
    const fresh = await fetchRevisionsMeta(spaceId, signal);

    return fresh.length > 0 ? fresh[fresh.length - 1].revision : 0;
  }

  async function syncFromContext(
    spaceId: number,
    kind: RevisionKind,
    revision?: number,
    signal?: AbortSignal,
  ): Promise<void> {
    if (kind === 'draft') {
      const latest = revision ?? (await resolveLatestRevision(spaceId, signal));
      await fetchRevision(spaceId, latest, signal);
      activeContext.value = { spaceId, revision: latest, kind: 'draft' };
    } else {
      const rev = revision ?? (await resolveLatestRevision(spaceId, signal));
      await fetchRevision(spaceId, rev, signal);
      activeContext.value = { spaceId, revision: rev, kind: 'rev' };
    }
  }

  function clearContext() {
    activeContext.value = { spaceId: null, revision: null, kind: 'rev' };
  }

  async function commitDraft(
    spaceId: number,
    rules: Rule[],
    signal?: AbortSignal,
    removedCodes?: string[],
    sections?: AbilitySection[],
  ): Promise<SpaceRevision<Rule>> {
    const result = await getRuleSpaceApi().commitDraft(spaceId, rules, signal, removedCodes, sections);
    cachedRevisions.value.set(cacheKey(spaceId, result.revision), result);
    activeContext.value = { spaceId, revision: result.revision, kind: 'rev' };
    await fetchRevisionsMeta(spaceId, signal);
    sectionCatalog.discardDraft(spaceId);
    const current = spaceStore.currentSpace;
    if (current?.id === spaceId) {
      spaceStore.currentSpace = { ...current, revision: result.revision };
    }
    const listed = spaceStore.spaces.find((space) => space.id === spaceId);
    if (listed) listed.revision = result.revision;

    return result;
  }

  return {
    revisionsMeta,
    cachedRevisions,
    activeContext,
    activeRevision,
    effectiveRules,
    effectiveSections,
    fetchRevisionsMeta,
    fetchRevision,
    resolveLatestRevision,
    syncFromContext,
    clearContext,
    commitDraft,
  };
});
