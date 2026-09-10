import type { User } from '@/modules/Core/User/Dto/User';

/**
 * Пишет текущего актора в стор и уведомляет порт (вклады меню).
 */
export class CurrentUserSessionService {
  constructor(
    private readonly session: {
      setCurrent(user: User): void;
      setGuest(): void;
      clearCurrent(): void;
    },
    private readonly onActorChanged: (actor: User | null) => void,
  ) {}

  setCurrent(user: User): void {
    this.session.setCurrent(user);
    this.onActorChanged(user);
  }

  setGuest(): void {
    this.session.setGuest();
    this.onActorChanged(null);
  }

  clearCurrent(): void {
    this.session.clearCurrent();
    this.onActorChanged(null);
  }
}
