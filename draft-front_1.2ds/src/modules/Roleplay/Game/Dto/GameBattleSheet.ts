/** Лист состава боя из game.getBattleSheets. */
export interface GameBattleSheet {
  type: 'character' | 'npc';
  id: number;
  name: string;
  actualVersion: number;
  spaceId?: number;
  rulesRevision?: number;
  choices: Record<string, unknown>;
  sheet: Record<string, unknown>;
}
