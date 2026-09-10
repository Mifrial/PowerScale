import { CurrentUserSessionService } from '@/modules/Core/User/Service/CurrentUserSessionService';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { applyMenuContributions } from '@/modules/Core/UI/init';

export const currentUserSessionService = new CurrentUserSessionService(
  {
    setCurrent: (user) => useUserStore().setCurrent(user),
    setGuest: () => useUserStore().setGuest(),
    clearCurrent: () => useUserStore().clearCurrent(),
  },
  (actor) => applyMenuContributions(actor),
);
