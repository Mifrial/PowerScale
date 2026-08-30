import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

export interface CharacterSessionTarget {
  gameId: number;
  characterId: number;
  approvedCharacterVersion: CharacterVersion | null;
}
