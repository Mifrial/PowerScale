import { computed } from 'vue';
import { useUserCatalog } from '@/modules/Core/User/init';
import { initials as userInitials, displayName as userDisplayName } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';

export function useChatUsers() {
  const catalog = useUserCatalog();

  const userMap = computed<Map<number, User>>(() => {
    const map = new Map<number, User>();
    for (const u of catalog.users.value) {
      map.set(u.id, u);
    }

    return map;
  });

  function getUser(id: number): User | undefined {
    return userMap.value.get(id);
  }

  async function ensureUsers(ids: number[]): Promise<void> {
    await catalog.ensureUsers(ids);
  }

  function initials(u: User | undefined): string {
    if (!u) return '?';

    return userInitials(u.name, u.surname);
  }

  function displayName(u: User | undefined): string {
    if (!u) return '';

    return userDisplayName(u.name, u.surname, u.login);
  }

  return { userMap, getUser, ensureUsers, initials, displayName };
}
