import { CurrentUserSessionService } from '@/modules/Core/User/Service/CurrentUserSessionService';
import { useUserStore } from '@/modules/Core/User/Store/users';

export const currentUserSessionService = new CurrentUserSessionService({
  setCurrent: (user) => useUserStore().setCurrent(user),
  setGuest: () => useUserStore().setGuest(),
  clearCurrent: () => useUserStore().clearCurrent(),
});
