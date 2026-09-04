import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import type { CreateUserData } from '@/modules/Core/User/Dto/CreateUserData';
import type { UpdateUserData } from '@/modules/Core/User/Dto/UpdateUserData';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { FindPageResult } from '@/modules/Core/User/Dto/FindPageResult';
import { getUserApi } from '@/modules/Core/User/init';
import { initials } from '@/modules/Core/User/Utils/initials';
import { displayName } from '@/modules/Core/User/Utils/displayName';

export const useUserStore = defineStore('users', () => {
  const currentUser = ref<User | null>(null);
  const profileUser = ref<User | null>(null);
  const guestActor = ref(false);
  const users = ref<User[]>([]);
  const total = ref(0);
  const loading = ref(false);

  const username = computed(() => {
    if (guestActor.value) return 'Гость';
    if (!currentUser.value) return '';

    return displayName(currentUser.value.name, currentUser.value.surname, currentUser.value.login);
  });
  const userLogin = computed(() => currentUser.value?.login || '');
  const avatarLetters = computed(() => {
    if (guestActor.value) return '?';
    if (!currentUser.value) return '??';

    return initials(currentUser.value.name, currentUser.value.surname);
  });

  function setCurrent(user: User): void {
    guestActor.value = false;
    currentUser.value = user;
  }

  function setGuest(): void {
    currentUser.value = null;
    guestActor.value = true;
  }

  function clearCurrent(): void {
    currentUser.value = null;
    guestActor.value = false;
  }

  function setProfileUser(user: User | null): void {
    profileUser.value = user;
  }

  async function findPage(query: FindPageQuery, signal?: AbortSignal): Promise<FindPageResult<User>> {
    loading.value = true;
    try {
      const page = await getUserApi().findPage(query, signal);
      users.value = page.items;
      total.value = page.total;

      return page;
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return { items: [], total: 0 };
      console.error('findPage failed', e);
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function getUser(id: number, signal?: AbortSignal): Promise<User> {
    return getUserApi().getUser(id, signal);
  }

  async function getUsersByIds(ids: number[], signal?: AbortSignal): Promise<User[]> {
    return getUserApi().getUsersByIds(ids, signal);
  }

  async function ensureUsers(ids: number[]): Promise<void> {
    const missing = ids.filter((id) => !users.value.some((u) => u.id === id));
    if (!missing.length) return;
    try {
      const fetched = await getUsersByIds(missing);
      for (const u of fetched) {
        if (!users.value.some((x) => x.id === u.id)) users.value.push(u);
      }
    } catch {
      // Best-effort прогрев каталога: профили догрузятся при следующем обращении (плейсхолдер аватара/имени).
    }
  }

  async function createUser(data: CreateUserData, signal?: AbortSignal): Promise<User> {
    const user = await getUserApi().createUser(data, signal);
    users.value.push(user);

    return user;
  }

  async function updateUser(id: number, data: UpdateUserData, signal?: AbortSignal): Promise<User> {
    const user = await getUserApi().updateUser(id, data, signal);
    const idx = users.value.findIndex((u) => u.id === id);
    if (idx !== -1) users.value[idx] = user;

    return user;
  }

  async function deactivateUser(
    id: number,
    reason?: string,
    deactivatedUntil?: string,
    signal?: AbortSignal,
  ): Promise<void> {
    await getUserApi().deactivateUser(id, reason, deactivatedUntil, signal);
    const user = users.value.find((u) => u.id === id);
    if (user) {
      user.active = false;
      user.deactivateReason = reason;
      user.deactivatedUntil = deactivatedUntil ? Math.floor(Date.parse(`${deactivatedUntil}T00:00:00Z`) / 1000) : null;
    }
  }

  return {
    currentUser,
    profileUser,
    users,
    total,
    loading,
    username,
    userLogin,
    avatarLetters,
    guestActor,
    setCurrent,
    setGuest,
    clearCurrent,
    setProfileUser,
    findPage,
    getUser,
    getUsersByIds,
    ensureUsers,
    createUser,
    updateUser,
    deactivateUser,
  };
});
