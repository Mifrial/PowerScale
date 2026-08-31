import { computed } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { displayName } from '@/modules/Core/User/Utils/displayName';
import { initials } from '@/modules/Core/User/Utils/initials';

export function useCurrentUser() {
  const store = useUserStore();

  const currentUser = computed(() => store.currentUser);
  const username = computed(() =>
    currentUser.value ? displayName(currentUser.value.name, currentUser.value.surname, currentUser.value.login) : '',
  );
  const userLogin = computed(() => currentUser.value?.login || '');
  const avatarLetters = computed(() => {
    if (!currentUser.value) return '??';
    if (currentUser.value.id === 0) return '?';

    return initials(currentUser.value.name, currentUser.value.surname);
  });
  const isGuest = computed(() => currentUser.value?.id === 0);
  const userId = computed(() => {
    const user = currentUser.value;
    if (!user || user.id === 0) return null;

    return user.id;
  });

  return { currentUser, username, userLogin, avatarLetters, isGuest, userId };
}
