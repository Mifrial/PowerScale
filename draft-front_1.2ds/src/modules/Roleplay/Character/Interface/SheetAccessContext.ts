import type { User } from '@/modules/Core/User/Dto/User';

/**
 * Контекст оценки видимости листа: зритель, владелец, чей лист и в каком контексте.
 * `ownerId` — владелец листа (null у НПС — нет владельца-игрока).
 * `gameId` — контекст игры (участники = 'all'); null — standalone-страница персонажа.
 */
export interface SheetAccessContext {
  user: User;
  ownerId: number | null;
  characterId: number;
  gameId: number | null;
}
