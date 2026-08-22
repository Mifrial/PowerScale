import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { registerSpaceApi } from '@/modules/Roleplay/Space/init';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { mockSpaceApi } from '@/modules/Roleplay/Space/Mock/mockSpaceApi';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useDraftRuleStore } from '@/modules/Roleplay/Rule/Store/draftRules';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

beforeEach(() => {
  localStorage.clear();
  setActivePinia(createPinia());
  serviceLocator.reset();
  registerSpaceApi(mockSpaceApi);
});

function freshRule(id: string): Rule {
  return {
    id,
    code: `code-${id}`,
    type: 'simple',
    name: `Правило ${id}`,
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
  };
}

describe('effectiveRules', () => {
  it('в контексте rev возвращает опубликованные правила ревизии', async () => {
    const store = useSpaceRevisionStore();
    const rev = await store.fetchRevision(1, 5);
    store.activeContext = { spaceId: 1, revision: 5, kind: 'rev' };

    expect(store.effectiveRules).toEqual(rev.rules);
  });

  it('в контексте draft применяет изменения по id и добавляет новые в конец', async () => {
    const store = useSpaceRevisionStore();
    const draftStore = useDraftRuleStore();
    const rev = await store.fetchRevision(1, 5);
    const published = rev.rules;

    const base = published[0];
    draftStore.saveRule(1, { ...base, name: 'Изменённое' });
    draftStore.saveRule(1, freshRule('draft-new'));
    store.activeContext = { spaceId: 1, revision: 5, kind: 'draft' };

    const merged = store.effectiveRules;
    expect(merged.find((r) => r.id === base.id)?.name).toBe('Изменённое');
    expect(merged[merged.length - 1].id).toBe('draft-new');
    expect(merged.length).toBe(published.length + 1);
  });

  it('без черновиков effectiveRules возвращает published', async () => {
    const store = useSpaceRevisionStore();
    const rev = await store.fetchRevision(1, 5);
    store.activeContext = { spaceId: 1, revision: 5, kind: 'draft' };

    expect(store.effectiveRules).toEqual(rev.rules);
  });
});

describe('resolveLatestRevision', () => {
  it('берёт последнюю ревизию из закэшированной meta', async () => {
    const store = useSpaceRevisionStore();
    store.revisionsMeta.set(2, [
      { revision: 3, publishedAt: '2026-01-01T00:00:00Z', ruleCount: 1, changedCount: 1 },
      { revision: 4, publishedAt: '2026-01-02T00:00:00Z', ruleCount: 1, changedCount: 1 },
    ]);

    await expect(store.resolveLatestRevision(2)).resolves.toBe(4);
  });

  it('без meta подгружает список ревизий и возвращает последнюю', async () => {
    const store = useSpaceRevisionStore();

    await expect(store.resolveLatestRevision(1)).resolves.toBe(5);
  });
});

describe('syncFromContext', () => {
  it('draft: грузит базовую ревизию и ставит draft-контекст', async () => {
    const store = useSpaceRevisionStore();

    await store.syncFromContext(1, 'draft', 5);

    expect(store.activeContext).toEqual({ spaceId: 1, revision: 5, kind: 'draft' });
    expect(store.activeRevision?.revision).toBe(5);
  });

  it('rev: грузит указанную ревизию и ставит rev-контекст', async () => {
    const store = useSpaceRevisionStore();

    await store.syncFromContext(1, 'rev', 3);

    expect(store.activeContext).toEqual({ spaceId: 1, revision: 3, kind: 'rev' });
  });
});

describe('commitDraft', () => {
  it('кэширует новую ревизию, переключает контекст и перечитывает meta', async () => {
    const store = useSpaceRevisionStore();
    const rev = await store.fetchRevision(1, 5);

    const result = await store.commitDraft(1, rev.rules);

    expect(result.revision).toBe(6);
    expect(store.activeContext).toEqual({ spaceId: 1, revision: 6, kind: 'rev' });
    expect(store.activeRevision?.revision).toBe(6);
    expect(store.cachedRevisions.get('1:6')?.revision).toBe(6);

    const meta = store.revisionsMeta.get(1) ?? [];
    expect(meta.length).toBeGreaterThan(0);
    expect(meta[meta.length - 1].revision).toBe(6);
  });
});
