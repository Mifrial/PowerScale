import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';

/** Ответ game.declareAction. */
export interface GamePhpActionResult {
  battleId: number;
  version: number;
  spent: number;
  sheetVersion: number;
  targetKey?: string;
  reaction?: string;
  pendingActionEffects: Record<CombatEntityKey, PendingActionEffect[]>;
}
