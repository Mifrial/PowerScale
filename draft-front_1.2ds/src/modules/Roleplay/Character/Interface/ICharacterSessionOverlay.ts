import type { CharacterSessionTarget } from '@/modules/Roleplay/Character/Dto/CharacterSessionTarget';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

/** Сессионный оверлей листа: Character mock вызывает, Game mock регистрирует. */
export interface ICharacterSessionOverlay {
  sessionTarget(characterId: number, gameId?: number): CharacterSessionTarget | null;
  writeSheet(gameId: number, characterId: number, version: CharacterVersion): Promise<void>;
  readSheet(gameId: number, characterId: number): CharacterVersion | null;
  syncLatestToMemberships(characterId: number): void;
}
