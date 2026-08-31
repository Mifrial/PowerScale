import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { SpaceRevisionMeta } from '@/modules/Roleplay/Space/Dto/SpaceRevisionMeta';
import type { SpaceRevision } from '@/modules/Roleplay/Space/Dto/SpaceRevision';
import type { RevisionKind } from '@/modules/Roleplay/Space/Enum/RevisionKind';
import type { RevisionContext } from '@/modules/Roleplay/Space/Dto/RevisionContext';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { getSpaceApi } from '@/modules/Roleplay/Space/init';
import { useRuleDrafts } from '@/modules/Roleplay/Rule/init';

export const useSpaceRevisionStore = defineStore('spaceRevision', () => {
  const drafts = useRuleDrafts();
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

  function cacheKey(spaceId: number, revision: number): string {
    return `${spaceId}:${revision}`;
  }

  async function fetchRevisionsMeta(spaceId: number, signal?: AbortSignal): Promise<SpaceRevisionMeta[]> {
    const api = getSpaceApi();
    const meta = await api.getRevisions(spaceId, signal);
    revisionsMeta.value.set(spaceId, meta);

    return meta;
  }

  async function fetchRevision(spaceId: number, revision: number, signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
    const key = cacheKey(spaceId, revision);
    const cached = cachedRevisions.value.get(key);
    if (cached) return cached;

    const rev = await getSpaceApi().getRevision(spaceId, revision, signal);
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
  ): Promise<SpaceRevision<Rule>> {
    const result = await getSpaceApi().commitDraft(spaceId, rules, signal, removedCodes);
    cachedRevisions.value.set(cacheKey(spaceId, result.revision), result);
    activeContext.value = { spaceId, revision: result.revision, kind: 'rev' };
    await fetchRevisionsMeta(spaceId, signal);

    return result;
  }

  return {
    revisionsMeta,
    cachedRevisions,
    activeContext,
    activeRevision,
    effectiveRules,
    fetchRevisionsMeta,
    fetchRevision,
    resolveLatestRevision,
    syncFromContext,
    clearContext,
    commitDraft,
  };
});
