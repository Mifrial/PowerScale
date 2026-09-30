import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';

/** Validate-only вход для нового персонажа или existing actual. */
export type CharacterValidateRequest =
  | { mode: 'create'; choices: CharacterBuild; creationConfig: CharacterCreationConfig }
  | { mode: 'update'; characterId: number; patch: CharacterPatch };
