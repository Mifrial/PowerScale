import { beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerUserApi } from '@/modules/Core/User/init';
import { mockUserApi } from '@/modules/Core/User/Mock/mockUserApi';
import { useUserSearch } from '@/modules/Core/User/Composables/useUserSearch';
import type { IUserApi } from '@/modules/Core/User/Interface/IUserApi';

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
});

describe('useUserSearch', () => {
  it('на пустой запрос просит десятку пользователей', async () => {
    const findPage = vi.fn<IUserApi['findPage']>().mockResolvedValue({ items: [], total: 0 });
    registerUserApi({ ...mockUserApi, findPage });

    const scope = effectScope();
    const search = scope.run(() => useUserSearch());
    expect(search).toBeDefined();

    await search?.loadInitial();

    expect(findPage).toHaveBeenCalledTimes(1);
    expect(findPage.mock.calls[0][0]).toEqual({
      limit: 10,
      offset: 0,
      q: undefined,
      active: true,
    });
    scope.stop();
  });
});
