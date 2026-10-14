import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';

/** Вход изменения existing actual: typed patch + whole-operation CAS. PHP пишет choices. */
export interface CharacterUpdateRequest {
  patch: CharacterPatch;
  choices?: CharacterBuild;
  creationConfig?: CharacterCreationConfig;
}
