import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';

/** Полная игра (ТР §3 `games` + участники): лимиты ОС/ОЛ/ОР/денег (null — лимит не задан), теги. */
export interface GameDetail {
  game: Game;
  description: string | null;
  osPointsLimit: number | null;
  olPointsLimit: number | null;
  orPointsLimit: number | null;
  moneyLimit: number | null;
  forbiddenTags: string[];
  members: GameMember[];
  /** Дубль `game.discussionChatId` для вкладок карточки; источник истины — поле списка. */
  discussionChatId: number | null;
  /** Дубль `game.gameChatId` для вкладки «Чат игры»; источник истины — поле списка. */
  gameChatId: number | null;
  /**
   * Личные заметки текущего зрителя по этой игре (не общие, не заметки владельца игры).
   * Другие участники и ведущий чужой текст не получают.
   */
  personalNotes?: string | null;
}
