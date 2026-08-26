import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { GameVisibility } from '@/modules/Roleplay/Game/Enum/GameVisibility';
import type { GameJoinPolicy } from '@/modules/Roleplay/Game/Enum/GameJoinPolicy';

/** Карточка игры в списке (ТР §3 `games`; денормализованные поля — представление списка). */
export interface Game {
  id: number;
  name: string;
  shortDescription: string | null;
  status: GameStatus;
  visibility: GameVisibility;
  joinPolicy: GameJoinPolicy;
  ownerId: number;
  ownerName: string;
  spaceId: number;
  spaceCode: string;
  rulesRevision: number;
  memberCount: number;
  tags: string[];
  /** Игровой чат (type `game`); null — ещё не создан. На списке, чтобы резолв чипов не ходил в деталки. */
  gameChatId: number | null;
  /** Чат обсуждения игры (type `game_discussion`); null — ещё не создан. */
  discussionChatId: number | null;
}
