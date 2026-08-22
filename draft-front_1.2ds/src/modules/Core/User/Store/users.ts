import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import type { CreateUserData } from '@/modules/Core/User/Dto/CreateUserData';
import type { UpdateUserData } from '@/modules/Core/User/Dto/UpdateUserData';
import { getUserApi } from '@/modules/Core/User/init';
import { initials } from '@/modules/Core/User/Utils/initials';
import { displayName } from '@/modules/Core/User/Utils/displayName';

export const useUserStore = defineStore('users', () => {
  const currentUser = ref<User | null>(null);
  const users = ref<User[]>([]);
  const loading = ref(false);

  const username = computed(() =>
    currentUser.value ? displayName(currentUser.value.name, currentUser.value.surname, currentUser.value.login) : '',
  );
  const userLogin = computed(() => currentUser.value?.login || '');
  const avatarLetters = computed(() => {
    if (!currentUser.value) return '??';
    if (currentUser.value.id === 0) return '?';

    return initials(currentUser.value.name, currentUser.value.surname);
  });

  function setCurrent(user: User): void {
    currentUser.value = user;
  }

  function setGuest(): void {
    currentUser.value = {
      id: 0,
      name: 'Гость',
      login: 'guest',
      email: '',
      groups: ['Гость'],
      registered: '',
      active: true,
      nickname: 'guest',
    };
  }

  function clearCurrent(): void {
    currentUser.value = null;
  }

  async function fetchUsers(signal?: AbortSignal) {
    loading.value = true;
    try {
      users.value = await getUserApi().getUsers(signal);
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      console.error('fetchUsers failed', e);
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
    if (!users.value.length) {
      await fetchUsers();

      return;
    }
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
      user.deactivate_reason = reason;
      user.deactivated_until = deactivatedUntil;
    }
  }

  return {
    currentUser,
    users,
    loading,
    username,
    userLogin,
    avatarLetters,
    setCurrent,
    setGuest,
    clearCurrent,
    fetchUsers,
    getUser,
    getUsersByIds,
    ensureUsers,
    createUser,
    updateUser,
    deactivateUser,
  };
});
