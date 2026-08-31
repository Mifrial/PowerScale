import { computed } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';

export function useUserCatalog() {
  const store = useUserStore();

  const users = computed(() => store.users);

  function findByLogin(login: string) {
    return store.users.find((user) => user.login === login);
  }

  return {
    users,
    findByLogin,
    ensureUsers: (ids: number[]) => store.ensureUsers(ids),
    getUser: (id: number, signal?: AbortSignal) => store.getUser(id, signal),
  };
}
