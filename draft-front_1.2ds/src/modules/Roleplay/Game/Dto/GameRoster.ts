import type { GameRosterNpc } from '@/modules/Roleplay/Game/Dto/GameRosterNpc';
import type { GameRosterCharacter } from '@/modules/Roleplay/Game/Dto/GameRosterCharacter';

/** Ответ game.getRoster. */
export interface GameRoster {
  characters: GameRosterCharacter[];
  npcs: GameRosterNpc[];
}
