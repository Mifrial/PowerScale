import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import {
  captureCharacterRuntimeState,
  getCharacterActualVersion,
  getStoredCharacterVersion,
  replaceCharacterRuntimeVersion,
  restoreCharacterRuntimeState,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { characterPatchService } from '@/modules/Roleplay/Character/init';
import type { IGameCharacterRuntimeMutationPort } from '@/modules/Roleplay/Game/Interface/IGameCharacterRuntimeMutationPort';

/**
 * Адаптирует typed Character patch к in-memory actual storage mock.
 * Реальный backend gateway заменяет этот объект без изменения Game command.
 */
export class MockGameCharacterRuntimeMutationPort implements IGameCharacterRuntimeMutationPort {
  capture(characterId: number): CharacterDetail {
    return captureCharacterRuntimeState(characterId);
  }

  applyPatch(characterId: number, patch: CharacterPatch): CharacterDetail {
    const current = getStoredCharacterVersion(characterId);
    if (getCharacterActualVersion(characterId) !== patch.expectedActualVersion) {
      throw new Error('Актуальное состояние персонажа уже изменилось');
    }

    const next = characterPatchService.applyPatch(current, patch.operations);

    return replaceCharacterRuntimeVersion(characterId, next, patch.expectedActualVersion);
  }

  restore(characterId: number, snapshot: CharacterDetail): void {
    restoreCharacterRuntimeState(characterId, snapshot);
  }
}
