import { describe, it, expect, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { registerRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/init';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { mockRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/Mock/mockRuleSpaceApi';
import { useSpaceStore } from '@/modules/Roleplay/RuleSpace/Store/spaces';
import type { Space } from '@/modules/Roleplay/RuleSpace/Dto/Space';

const spaceA: Space = {
  id: 1,
  code: 'razrabotka',
  name: 'Разработка',
  description: '',
  ownerId: 1,
  revision: 5,
  active: true,
  createdAt: 1,
  rulesCount: 0,
};

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
});

describe('fetchSpaceByCode', () => {
  it('успешный ответ после abort не пишет currentSpace', async () => {
    const controller = new AbortController();
    let release: (space: Space) => void = () => undefined;
    registerRuleSpaceApi({
      ...mockRuleSpaceApi,
      getSpaceByCode: () =>
        new Promise((resolve) => {
          release = resolve;
        }),
    });
    const store = useSpaceStore();
    const pending = store.fetchSpaceByCode('razrabotka', controller.signal);
    controller.abort();
    release(spaceA);

    await expect(pending).rejects.toMatchObject({ name: 'AbortError' });
    expect(store.currentSpace).toBeNull();
  });

  it('ответ с другим code не пишет currentSpace', async () => {
    registerRuleSpaceApi({
      ...mockRuleSpaceApi,
      getSpaceByCode: async () => ({ ...spaceA, code: 'actual' }),
    });
    const store = useSpaceStore();

    await expect(store.fetchSpaceByCode('razrabotka')).rejects.toThrow(
      'Ответ не соответствует запрошенному пространству',
    );
    expect(store.currentSpace).toBeNull();
  });
});
