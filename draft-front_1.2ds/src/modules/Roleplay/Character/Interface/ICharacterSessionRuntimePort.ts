import type { CharacterSessionTarget } from '@/modules/Roleplay/Character/Dto/CharacterSessionTarget';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

/** Target-neutral active-session seam; actual remains the only sheet source. */
export interface ICharacterSessionRuntimePort {
  sessionTarget(characterId: number, gameId?: number): CharacterSessionTarget | null;
  applyActualPatch(gameId: number, characterId: number, patch: CharacterPatch): Promise<void>;
  readActual(gameId: number, characterId: number): CharacterVersion | null;
  syncLatestToMemberships(characterId: number): void;
}
