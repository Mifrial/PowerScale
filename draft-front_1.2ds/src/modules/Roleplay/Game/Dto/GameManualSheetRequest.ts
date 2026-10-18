import type { GamePhpWoundParty } from '@/modules/Roleplay/Game/Dto/GamePhpWoundParty';

/** Тело game.manualSheet. */
export interface GameManualSheetRequest {
  gameId: number;
  idempotencyKey: string;
  operation: 'setResource' | 'addState' | 'patchState' | 'removeState';
  target: GamePhpWoundParty;
  expectedSheetVersion: number;
  details: Record<string, unknown>;
}
