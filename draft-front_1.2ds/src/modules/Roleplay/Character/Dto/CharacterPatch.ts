import type { CharacterPatchOperation } from '@/modules/Roleplay/Character/Dto/CharacterPatchOperation';

/** Полная optimistic-lock команда editor/runtime mutation для existing actual. */
export interface CharacterPatch {
  commandId: string;
  expectedActualVersion: number;
  operations: CharacterPatchOperation[];
}
