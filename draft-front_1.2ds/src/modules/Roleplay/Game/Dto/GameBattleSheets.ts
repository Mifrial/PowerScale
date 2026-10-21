import type { GameBattleSheet } from '@/modules/Roleplay/Game/Dto/GameBattleSheet';
import type { GamePhpOpenWideStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenWideStrike';

/** Ответ game.getBattleSheets. */
export interface GameBattleSheets {
  battleId: number;
  characters: { type: 'character'; id: number; name: string; actualVersion: number }[];
  npcs: { type: 'npc'; id: number; name: string; actualVersion: number }[];
  sheets: GameBattleSheet[];
  openWideStrike?: GamePhpOpenWideStrike | null;
}
