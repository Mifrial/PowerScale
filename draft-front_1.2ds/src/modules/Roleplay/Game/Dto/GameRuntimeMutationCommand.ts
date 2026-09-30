import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Typed actual mutation command shared by editor, combat and loot mock paths. */
export interface GameRuntimeMutationCommand {
  commandId: string;
  gameId: number;
  entityKey: CombatEntityKey;
  patch: CharacterPatch;
}
