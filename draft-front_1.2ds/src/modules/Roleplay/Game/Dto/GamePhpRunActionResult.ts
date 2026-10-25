import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';
import type { GamePhpStrikeRow } from '@/modules/Roleplay/Game/Dto/GamePhpStrikeRow';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';

/** Нормализованный ответ game.runAction. */
export interface GamePhpRunActionResult {
  kind: 'action' | 'strike' | 'wound';
  phase: 'declare' | 'respond';
  battleId: number;
  version: number;
  strikeId?: number;
  debt?: boolean;
  closed?: boolean;
  strikes?: GamePhpStrikeRow[];
  pendingStrikes?: { index: number; target: GamePhpParticipant }[];
  spent?: number;
  sheetVersion?: number | null;
  targetKey?: string | null;
  reaction?: string | null;
  pendingActionEffects?: Record<CombatEntityKey, PendingActionEffect[]>;
  operation?: string;
  target?: GamePhpParticipant;
  stateId?: number;
  battleVersion?: number;
}
