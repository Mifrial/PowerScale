import type { GameBattleSheet } from '@/modules/Roleplay/Game/Dto/GameBattleSheet';

/** Ответ game.getSheets. */
export interface GameSheets {
  sheets: GameBattleSheet[];
  missing: { type: string; id: number }[];
}
