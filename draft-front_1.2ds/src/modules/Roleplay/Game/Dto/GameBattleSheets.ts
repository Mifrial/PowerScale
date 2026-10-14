import type { GameBattleSheet } from '@/modules/Roleplay/Game/Dto/GameBattleSheet';

/** Ответ game.getBattleSheets. */
export interface GameBattleSheets {
  battleId: number;
  characters: { type: 'character'; id: number; name: string; actualVersion: number }[];
  npcs: { type: 'npc'; id: number; name: string; actualVersion: number }[];
  sheets: GameBattleSheet[];
}
