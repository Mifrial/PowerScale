import type { GameInitiativeOrderEntry } from '@/modules/Roleplay/Game/Dto/GameInitiativeOrderEntry';

/** Ответ game.rollInitiative. */
export interface GameInitiativeRollResult {
  battleId: number;
  version: number;
  order: GameInitiativeOrderEntry[];
}
