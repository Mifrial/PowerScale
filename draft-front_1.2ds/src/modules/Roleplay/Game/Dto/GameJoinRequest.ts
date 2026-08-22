import type { GameJoinRequestStatus } from '@/modules/Roleplay/Game/Enum/GameJoinRequestStatus';

/**
 * Заявка на вступление в игру (по join_policy «любой желающий/друзья»): игрок подаёт заявку,
 * ведущий принимает (добавляет участником) или отклоняет.
 */
export interface GameJoinRequest {
  id: number;
  gameId: number;
  userId: number;
  userName: string;
  status: GameJoinRequestStatus;
  createdAt: string;
}
