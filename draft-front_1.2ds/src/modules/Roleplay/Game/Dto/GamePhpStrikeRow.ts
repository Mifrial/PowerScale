import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';
import type { GamePhpStrikeConditions } from '@/modules/Roleplay/Game/Dto/GamePhpStrikeConditions';

/** Строка удара в ответе game.runAction. */
export interface GamePhpStrikeRow {
  index: number;
  itemInventoryId: number;
  itemRuleCode: string;
  profileType: string;
  profileIndex: number;
  instanceIndex: number;
  target: GamePhpParticipant;
  reaction: string | null;
  conditions?: GamePhpStrikeConditions;
  roll?: unknown;
  defenseRoll?: unknown;
  success?: number | null;
  damage?: unknown;
  resistance?: unknown;
  injury?: unknown;
  sheetVersion?: number | null;
  code?: string | null;
  [key: string]: unknown;
}
