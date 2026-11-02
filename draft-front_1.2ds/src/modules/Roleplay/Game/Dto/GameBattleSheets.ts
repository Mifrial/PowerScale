import type { GameBattleSheet } from '@/modules/Roleplay/Game/Dto/GameBattleSheet';
import type { GamePhpOpenWideStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenWideStrike';
import type { GamePhpProcessSessionView } from '@/modules/Roleplay/Game/Dto/GamePhpProcessSessionView';

/** Ответ game.getBattleSheets. */
export interface GameBattleSheets {
  battleId: number;
  characters: { type: 'character'; id: number; name: string; actualVersion: number }[];
  npcs: { type: 'npc'; id: number; name: string; actualVersion: number }[];
  sheets: GameBattleSheet[];
  openWideStrike?: GamePhpOpenWideStrike | null;
  /** Открытые сессии процесса по ключам участников боя. */
  processSessions?: Record<string, GamePhpProcessSessionView>;
  /** Слоты долга ОД по ключам участников боя. */
  actionDebt?: Record<string, unknown>;
}
