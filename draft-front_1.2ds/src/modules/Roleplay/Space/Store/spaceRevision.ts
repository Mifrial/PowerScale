import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { SpaceRevisionMeta } from '@/modules/Roleplay/Space/Dto/SpaceRevisionMeta'
import type { SpaceRevision } from '@/modules/Roleplay/Space/Dto/SpaceRevision'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import { getSpaceApi } from '../init'
import { useDraftRuleStore } from '@/modules/Roleplay/Rule/Store/draftRules'

export type RevisionKind = 'draft' | 'rev'

export interface RevisionContext {
  spaceId: number | null
  revision: number | null
  kind: RevisionKind
}

export const useSpaceRevisionStore = defineStore('spaceRevision', () => {
  const revisionsMeta = ref<Map<number, SpaceRevisionMeta[]>>(new Map())
  const cachedRevisions = ref<Map<string, SpaceRevision<Rule>>>(new Map())
  const loading = ref(false)

  const activeContext = ref<RevisionContext>({ spaceId: null, revision: null, kind: 'rev' })

  const activeRevision = computed<SpaceRevision<Rule> | null>(() => {
    if (!activeContext.value.spaceId || activeContext.value.revision === null) return null
    return cachedRevisions.value.get(cacheKey(activeContext.value.spaceId, activeContext.value.revision)) ?? null
  })

  const effectiveRules = computed<Rule[]>(() => {
    const ctx = activeContext.value
    if (!ctx.spaceId) return []
    const revision = activeRevision.value
    const published = revision?.rules ?? []

    if (ctx.kind === 'rev') return published

    const draftStore = useDraftRuleStore()
    const draftRules = draftStore.getDraftRules(ctx.spaceId)
    if (draftRules.length === 0) return published

    const draftMap = new Map(draftRules.map(r => [r.id, r]))
    const merged = published.map(r => draftMap.get(r.id) ?? r)
    const newRules = draftRules.filter(r => !published.some(p => p.id === r.id))
    return [...merged, ...newRules]
  })

  function cacheKey(spaceId: number, revision: number): string {
    return `${spaceId}:${revision}`
  }

  async function fetchRevisionsMeta(spaceId: number, signal?: AbortSignal): Promise<SpaceRevisionMeta[]> {
    const api = getSpaceApi()
    const meta = await api.getRevisions(spaceId, signal)
    revisionsMeta.value.set(spaceId, meta)
    return meta
  }

  async function fetchRevision(spaceId: number, revision: number, signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
    const key = cacheKey(spaceId, revision)
    const cached = cachedRevisions.value.get(key)
    if (cached) return cached

    loading.value = true
    try {
      const rev = await getSpaceApi().getRevision(spaceId, revision, signal)
      cachedRevisions.value.set(key, rev)
      return rev
    } finally {
      loading.value = false
    }
  }

  async function resolveLatestRevision(spaceId: number, signal?: AbortSignal): Promise<number> {
    const meta = revisionsMeta.value.get(spaceId)
    if (meta && meta.length > 0) return meta[meta.length - 1].revision
    const fresh = await fetchRevisionsMeta(spaceId, signal)
    return fresh.length > 0 ? fresh[fresh.length - 1].revision : 0
  }

  async function syncFromContext(
    spaceId: number,
    kind: RevisionKind,
    revision?: number,
    signal?: AbortSignal,
  ): Promise<void> {
    if (kind === 'draft') {
      const latest = revision ?? await resolveLatestRevision(spaceId, signal)
      await fetchRevision(spaceId, latest, signal)
      activeContext.value = { spaceId, revision: latest, kind: 'draft' }
    } else {
      const rev = revision ?? await resolveLatestRevision(spaceId, signal)
      await fetchRevision(spaceId, rev, signal)
      activeContext.value = { spaceId, revision: rev, kind: 'rev' }
    }
  }

  function clearContext() {
    activeContext.value = { spaceId: null, revision: null, kind: 'rev' }
  }

  function invalidateCache(spaceId: number, revision: number) {
    cachedRevisions.value.delete(cacheKey(spaceId, revision))
    revisionsMeta.value.delete(spaceId)
  }

  async function commitDraft(spaceId: number, rules: Rule[], signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
    const result = await getSpaceApi().commitDraft(spaceId, rules, signal)
    cachedRevisions.value.set(cacheKey(spaceId, result.revision), result)
    revisionsMeta.value.delete(spaceId)
    activeContext.value = { spaceId, revision: result.revision, kind: 'rev' }
    return result
  }

  return {
    revisionsMeta,
    cachedRevisions,
    loading,
    activeContext,
    activeRevision,
    effectiveRules,
    fetchRevisionsMeta,
    fetchRevision,
    resolveLatestRevision,
    syncFromContext,
    clearContext,
    invalidateCache,
    commitDraft,
  }
})
