import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerUserApi, useUserCatalog } from '@/modules/Core/User/init';
import { mockUserApi } from '@/modules/Core/User/Mock/mockUserApi';
import { users as mockUsers } from '@/modules/Core/User/Mock/mockUsers';
import { useUserStore } from '@/modules/Core/User/Store/users';
import type { IUserApi } from '@/modules/Core/User/Interface/IUserApi';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
});

function apiWith(findPage: IUserApi['findPage']): IUserApi {
  return { ...mockUserApi, findPage };
}

describe('useUserCatalog', () => {
  it('находит профиль по логину и пополняет каталог', async () => {
    const target = mockUsers[1];
    const queries: FindPageQuery[] = [];
    registerUserApi(
      apiWith((query) => {
        queries.push(query);
        const matched = mockUsers.filter((user) => user.login === target.login);

        return Promise.resolve({ items: matched, total: matched.length });
      }),
    );
    const catalog = useUserCatalog();
    const store = useUserStore();

    const found = await catalog.resolveByLogin(target.login);

    expect(found?.id).toBe(target.id);
    expect(queries[0]?.q).toBe(target.login);
    expect(store.users.some((user) => user.id === target.id)).toBe(true);
  });

  it('не ходит в API, если профиль уже в каталоге', async () => {
    let calls = 0;
    registerUserApi(
      apiWith(() => {
        calls += 1;

        return Promise.resolve({ items: [], total: 0 });
      }),
    );
    const catalog = useUserCatalog();
    useUserStore().rememberUsers([mockUsers[0]]);

    const found = await catalog.resolveByLogin(mockUsers[0].login);

    expect(found?.id).toBe(mockUsers[0].id);
    expect(calls).toBe(0);
  });

  it('возвращает null, если логина нет', async () => {
    registerUserApi(apiWith(() => Promise.resolve({ items: [], total: 0 })));
    const catalog = useUserCatalog();

    expect(await catalog.resolveByLogin('nobody')).toBeNull();
    expect(await catalog.resolveByLogin('')).toBeNull();
  });
});
