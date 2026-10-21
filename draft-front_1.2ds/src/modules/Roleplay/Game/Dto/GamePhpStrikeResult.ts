import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Ответ declare/resolve удара. */
export interface GamePhpStrikeResult {
  version: number;
  debt?: boolean;
  strikeId?: number;
  closed?: boolean;
  pendingTargets?: GamePhpParticipant[];
  targetResults?: unknown[];
}
