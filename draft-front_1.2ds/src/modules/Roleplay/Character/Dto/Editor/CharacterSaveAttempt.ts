import type { CharacterCreateRequest } from '@/modules/Roleplay/Character/Dto/CharacterCreateRequest';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';

/** Повторяемая попытка Character mutation с тем же command payload для idempotent retry. */
export type CharacterSaveAttempt =
  | {
      kind: 'create';
      request: CharacterCreateRequest;
    }
  | {
      kind: 'update';
      characterId: number;
      patch: CharacterPatch;
      choices: CharacterBuild;
      creationConfig: CharacterCreationConfig;
    };
