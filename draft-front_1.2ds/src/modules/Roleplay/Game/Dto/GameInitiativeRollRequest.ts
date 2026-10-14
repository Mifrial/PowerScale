import type { GameInitiativeRoll } from '@/modules/Roleplay/Game/Dto/GameInitiativeRoll';

/** Тело game.rollInitiative. */
export interface GameInitiativeRollRequest {
  gameId: number;
  battleId: number;
  idempotencyKey: string;
  expectedVersion: number;
  rolls: GameInitiativeRoll[];
}
