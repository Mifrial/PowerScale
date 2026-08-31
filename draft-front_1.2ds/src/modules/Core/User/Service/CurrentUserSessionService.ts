import type { User } from '@/modules/Core/User/Dto/User';

export class CurrentUserSessionService {
  constructor(
    private readonly session: {
      setCurrent(user: User): void;
      setGuest(): void;
      clearCurrent(): void;
    },
  ) {}

  setCurrent(user: User): void {
    this.session.setCurrent(user);
  }

  setGuest(): void {
    this.session.setGuest();
  }

  clearCurrent(): void {
    this.session.clearCurrent();
  }
}
