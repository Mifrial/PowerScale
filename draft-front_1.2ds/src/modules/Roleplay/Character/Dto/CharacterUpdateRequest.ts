import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';

/** Вход изменения existing actual: typed patch + whole-operation CAS. */
export interface CharacterUpdateRequest {
  patch: CharacterPatch;
}
