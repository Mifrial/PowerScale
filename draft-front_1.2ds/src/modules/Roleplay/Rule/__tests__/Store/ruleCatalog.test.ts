import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerRuleApi } from '@/modules/Roleplay/Rule/init';
import { useRuleCatalogStore } from '@/modules/Roleplay/Rule/Store/ruleCatalog';
import type { IRuleApi } from '@/modules/Roleplay/Rule/Interface/IRuleApi';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const catalogRules: Rule[] = [
  {
    id: 'r-1',
    code: 'movement',
    type: 'simple',
    name: 'Перемещение',
    description: '',
    spaceId: 0,
    createdAt: '2026-01-01T00:00:00Z',
  },
  {
    id: 'r-2',
    code: 'fire-bolt',
    type: 'ability',
    name: 'Огненная стрела',
    description: '',
    spaceId: 0,
    createdAt: '2026-01-01T00:00:00Z',
  },
];

function catalogApi(getRules: (spaceId: number) => Promise<Rule[]>): IRuleApi {
  return {
    getRules,
    getRule: async () => {
      throw new Error('not implemented');
    },
    getRuleVersions: async () => [],
    createRule: async () => {
      throw new Error('not implemented');
    },
    updateRule: async () => {
      throw new Error('not implemented');
    },
    deleteRule: async () => {},
    getMechanics: async () => [],
  };
}

beforeEach(() => {
  setActivePinia(createPinia());
  serviceLocator.reset();
});

describe('rule catalog store', () => {
  it('ensureLoaded loads catalog and findRule resolves by code', async () => {
    registerRuleApi(catalogApi(async () => catalogRules));
    const store = useRuleCatalogStore();

    await store.ensureLoaded();

    expect(store.rules.length).toBe(2);
    expect(store.findRule('fire-bolt')?.name).toBe('Огненная стрела');
    expect(store.findRule('missing')).toBeUndefined();
  });

  it('ensureLoaded does not fetch again once catalog is loaded', async () => {
    const getRules = vi.fn(async () => catalogRules);
    registerRuleApi(catalogApi(getRules));
    const store = useRuleCatalogStore();

    await store.ensureLoaded();
    await store.ensureLoaded();

    expect(getRules).toHaveBeenCalledTimes(1);
  });

  it('ensureLoaded rejects when the api fails', async () => {
    registerRuleApi(
      catalogApi(async () => {
        throw new Error('boom');
      }),
    );
    const store = useRuleCatalogStore();

    await expect(store.ensureLoaded()).rejects.toThrow('boom');
    expect(store.rules.length).toBe(0);
  });
});
