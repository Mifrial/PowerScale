import { displayName, initials } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';

/**
 * Подпись заявителя на вступление: имя и инициал из карточки User.
 * Заявка Game несёт только userId; пока каталог не ответил — запасной вид по id.
 */
export class JoinRequestCaptionService {
  caption(user: User | undefined, userId: number): { name: string; initials: string } {
    if (!user) {
      return { name: this.fallback(userId), initials: '?' };
    }
    const name = displayName(user.name, user.surname, user.login) || this.fallback(userId);

    return { name, initials: initials(user.name, user.surname) };
  }

  private fallback(userId: number): string {
    return `#${userId}`;
  }
}
