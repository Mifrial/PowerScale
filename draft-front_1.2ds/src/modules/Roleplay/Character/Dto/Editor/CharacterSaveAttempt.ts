import type { CharacterCreateRequest } from '@/modules/Roleplay/Character/Dto/CharacterCreateRequest';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';

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
    };
