import { computed } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';

export function useCurrentUser() {
  const store = useUserStore();

  const currentUser = computed(() => store.currentUser);
  const isGuest = computed(() => store.guestActor);
  const username = computed(() => store.username);
  const userLogin = computed(() => store.userLogin);
  const avatarLetters = computed(() => store.avatarLetters);
  const userId = computed(() => currentUser.value?.id ?? null);

  return { currentUser, username, userLogin, avatarLetters, isGuest, userId };
}
