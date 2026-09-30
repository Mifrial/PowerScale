import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';

export interface IGameCharacterRuntimeMutationPort {
  capture(characterId: number): CharacterDetail;
  applyPatch(characterId: number, patch: CharacterPatch): CharacterDetail;
  restore(characterId: number, snapshot: CharacterDetail): void;
}
