import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useDraftRuleStore } from '@/modules/Roleplay/Rule/Store/draftRules';
import { draftRulesPersistService } from '@/modules/Roleplay/Rule/Service/Instance/draftRulesPersistService';
import { DRAFT_RULES_STORAGE_KEY } from '@/modules/Roleplay/Rule/Constant/draftRulesConfig';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function makeRule(n: number): Rule {
  return {
    id: n,
    code: `code-${n}`,
    type: 'simple',
    name: `Правило ${n}`,
    description: '',
    spaceId: 1,
    createdAt: '2026-08-02T00:00:00Z',
  };
}

describe('draftRules store persistence', () => {
  beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('сохраняет черновик в localStorage и восстанавливает после пересоздания стора', () => {
    const store = useDraftRuleStore();
    store.saveRule(1, makeRule(1));
    store.saveRule(1, makeRule(2));
    store.saveRule(2, makeRule(3));

    const fresh = useDraftRuleStore();
    expect(fresh.getDraftRules(1)).toHaveLength(2);
    expect(
      fresh
        .getDraftRules(1)
        .map((r) => r.id)
        .sort(),
    ).toEqual([1, 2]);
    expect(fresh.getDraftRules(2).map((r) => r.code)).toEqual(['code-3']);
    expect(fresh.hasDraft(1)).toBe(true);
  });

  it('removedCodes делает hasDraft истинным и переживает persist', () => {
    const store = useDraftRuleStore();
    store.setRemovedCodes(1, ['gone']);
    expect(store.hasDraft(1)).toBe(true);
    expect(store.getRemovedCodes(1)).toEqual(['gone']);

    setActivePinia(createPinia());
    const fresh = useDraftRuleStore();
    expect(fresh.getRemovedCodes(1)).toEqual(['gone']);
  });

  it('saveRules пишет persist один раз', () => {
    const store = useDraftRuleStore();
    const write = vi.spyOn(draftRulesPersistService, 'write');
    try {
      store.saveRules(1, [makeRule(1), makeRule(2), makeRule(3)]);
      expect(write).toHaveBeenCalledTimes(1);
      expect(store.getDraftRules(1)).toHaveLength(3);
    } finally {
      write.mockRestore();
    }
  });

  it('discardDraft удаляет ключ из localStorage', () => {
    const store = useDraftRuleStore();
    store.saveRule(1, makeRule(1));
    store.discardDraft(1);
    expect(localStorage.getItem(DRAFT_RULES_STORAGE_KEY)).toBeNull();

    const fresh = useDraftRuleStore();
    expect(fresh.hasDraft(1)).toBe(false);
  });

  it('невалидный JSON в хранилище не ломает стор и помечает discarded', () => {
    localStorage.setItem(DRAFT_RULES_STORAGE_KEY, '{not json');
    const store = useDraftRuleStore();
    expect(store.hasDraft(1)).toBe(false);
    expect(store.storageDiscarded).toBe(true);
  });

  it('неподходящая структура игнорируется', () => {
    localStorage.setItem(DRAFT_RULES_STORAGE_KEY, JSON.stringify([{ foo: 1 }, 42]));
    const store = useDraftRuleStore();
    expect(store.getDraftRules(1)).toEqual([]);
  });
});
