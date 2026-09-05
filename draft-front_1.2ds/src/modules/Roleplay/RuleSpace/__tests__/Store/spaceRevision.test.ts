import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { registerSpaceApi, getSpaceApi } from '@/modules/Roleplay/Space/init';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { mockSpaceApi } from '@/modules/Roleplay/Space/Mock/mockSpaceApi';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useRuleDrafts } from '@/modules/Roleplay/Rule/init';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

beforeEach(() => {
  localStorage.clear();
  setActivePinia(createPinia());
  resetRegisteredApis();
  registerSpaceApi(mockSpaceApi);
});

function freshRule(id: number | null): Rule {
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

  it('в контексте draft применяет изменения по code и добавляет новые в конец', async () => {
    const store = useSpaceRevisionStore();
    const drafts = useRuleDrafts();
    const rev = await store.fetchRevision(1, 5);
    const published = rev.rules;

    const base = published[0];
    drafts.saveRule(1, { ...base, name: 'Изменённое' });
    drafts.saveRule(1, freshRule(null));
    store.activeContext = { spaceId: 1, revision: 5, kind: 'draft' };

    const merged = store.effectiveRules;
    expect(merged.find((r) => r.id === base.id)?.name).toBe('Изменённое');
    expect(merged[merged.length - 1].id).toBeNull();
    expect(merged.length).toBe(published.length + 1);
  });

  it('same code при разных storage id — overlay, не второе правило', async () => {
    const store = useSpaceRevisionStore();
    const drafts = useRuleDrafts();
    const rev = await store.fetchRevision(1, 5);
    const base = rev.rules[0];
    drafts.saveRule(1, { ...base, id: null, name: 'Из файла' });
    store.activeContext = { spaceId: 1, revision: 5, kind: 'draft' };

    const matches = store.effectiveRules.filter((r) => r.code === base.code);
    expect(matches).toHaveLength(1);
    expect(matches[0]?.name).toBe('Из файла');
    expect(store.effectiveRules.length).toBe(rev.rules.length);
  });

  it('в draft скрывает правила из removedCodes', async () => {
    const store = useSpaceRevisionStore();
    const drafts = useRuleDrafts();
    const rev = await store.fetchRevision(1, 5);
    const gone = rev.rules[0];
    drafts.setRemovedCodes(1, [gone.code]);
    store.activeContext = { spaceId: 1, revision: 5, kind: 'draft' };

    expect(store.effectiveRules.some((rule) => rule.code === gone.code)).toBe(false);
    expect(store.effectiveRules.length).toBe(rev.rules.length - 1);
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

describe('unpublished space', () => {
  it('effectiveRules в draft — только правила черновика', async () => {
    const space = await getSpaceApi().createSpace({ name: 'Из файла', description: '' });
    const store = useSpaceRevisionStore();
    const drafts = useRuleDrafts();
    drafts.saveRule(space.id, {
      id: null,
      code: 'from-file',
      type: 'simple',
      name: 'Из файла',
      description: '',
      spaceId: space.id,
      createdAt: '2026-01-01T00:00:00Z',
    });
    await store.syncFromContext(space.id, 'draft', 0);

    expect(store.effectiveRules.map((rule) => rule.code)).toEqual(['from-file']);
  });
});
