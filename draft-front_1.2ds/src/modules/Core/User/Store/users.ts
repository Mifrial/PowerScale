import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Ref } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import type { CreateUserData, UpdateUserData } from '@/modules/Core/User/Interface/IUserApi';
import { getUserApi } from '@/modules/Core/User/init';
import { initials, displayName } from '@/modules/Core/User/Utils/profile';

export const useUserStore = defineStore('users', () => {
  const currentUser = ref<User | null>(null);
  const users = ref<User[]>([]);
  const loading = ref(false);
  const quickFilter = ref('');
  const filterName = ref<{ mode: 'equals' | 'contains'; value: string } | null>(null);
  const filterSurname = ref<{ mode: 'equals' | 'contains'; value: string } | null>(null);
  const filterNickname = ref<{ mode: 'equals' | 'contains'; value: string } | null>(null);
  const filterLogin = ref<{ mode: 'equals' | 'contains'; value: string } | null>(null);
  const filterEmail = ref<{ mode: 'equals' | 'contains'; value: string } | null>(null);
  const filterActive = ref('');
  const filterLastLogin = ref('');

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

  const stringFilterConfigs: {
    filter: Ref<{ mode: 'equals' | 'contains'; value: string } | null>;
    getValue: (u: User) => string;
  }[] = [
    { filter: filterName, getValue: (u) => u.name },
    { filter: filterSurname, getValue: (u) => u.surname ?? '' },
    { filter: filterNickname, getValue: (u) => u.nickname ?? '' },
    { filter: filterLogin, getValue: (u) => u.login },
    { filter: filterEmail, getValue: (u) => u.email },
  ];

  function applyStringFilter(
    items: User[],
    filter: { mode: 'equals' | 'contains'; value: string } | null,
    getValue: (u: User) => string,
  ): User[] {
    if (!filter || !filter.value) return items;
    const q = filter.value.toLowerCase();
    if (filter.mode === 'equals') {
      return items.filter((u) => getValue(u).toLowerCase() === q);
    }

    return items.filter((u) => getValue(u).toLowerCase().includes(q));
  }

  const filteredUsers = computed(() => {
    let result = users.value;
    if (quickFilter.value) {
      const q = quickFilter.value.toLowerCase();
      result = result.filter(
        (u) =>
          u.name.toLowerCase().includes(q) ||
          (u.surname ?? '').toLowerCase().includes(q) ||
          (u.nickname ?? '').toLowerCase().includes(q) ||
          u.login.toLowerCase().includes(q) ||
          u.email.toLowerCase().includes(q),
      );
    }
    for (const config of stringFilterConfigs) {
      result = applyStringFilter(result, config.filter.value, config.getValue);
    }
    if (filterActive.value === 'true') {
      result = result.filter((u) => u.active);
    } else if (filterActive.value === 'false') {
      result = result.filter((u) => !u.active);
    }
    if (filterLastLogin.value) {
      const q = filterLastLogin.value.toLowerCase();
      result = result.filter((u) => (u.lastLogin ?? '').toLowerCase().includes(q));
    }

    return result;
  });

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
    quickFilter,
    filterName,
    filterSurname,
    filterNickname,
    filterLogin,
    filterEmail,
    filterActive,
    filterLastLogin,
    username,
    userLogin,
    avatarLetters,
    filteredUsers,
    setCurrent,
    setGuest,
    clearCurrent,
    fetchUsers,
    getUser,
    getUsersByIds,
    createUser,
    updateUser,
    deactivateUser,
  };
});
