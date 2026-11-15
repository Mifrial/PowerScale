import { computed } from 'vue';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { FindPageResult } from '@/modules/Core/User/Dto/FindPageResult';
import type { User } from '@/modules/Core/User/Dto/User';
import { useUserStore } from '@/modules/Core/User/Store/users';

/** Сколько профилей запрашивать при резолве логина из старого токена. */
const RESOLVE_LIMIT = 20;

export function useUserCatalog() {
  const store = useUserStore();

  const users = computed(() => store.users);

  function findByLogin(login: string) {
    return store.users.find((user) => user.login === login);
  }

  /**
   * Поиск с пополнением каталога: найденные профили остаются в сторе и переиспользуются чипами.
   */
  function findPage(query: FindPageQuery, signal?: AbortSignal): Promise<FindPageResult<User>> {
    return store.findPage(query, signal);
  }

  /**
   * Профиль по логину: сначала каталог, затем поиск по логину.
   * Нужен чипам `[[user:login]]`, когда участника нет в уже загруженном каталоге.
   */
  async function resolveByLogin(login: string, signal?: AbortSignal): Promise<User | null> {
    const known = findByLogin(login);
    if (known || !login) return known ?? null;
    const page = await findPage({ limit: RESOLVE_LIMIT, offset: 0, q: login }, signal);

    return page.items.find((user) => user.login === login) ?? null;
  }

  return {
    users,
    findByLogin,
    findPage,
    resolveByLogin,
    ensureUsers: (ids: number[]) => store.ensureUsers(ids),
    getUser: (id: number, signal?: AbortSignal) => store.getUser(id, signal),
  };
}
