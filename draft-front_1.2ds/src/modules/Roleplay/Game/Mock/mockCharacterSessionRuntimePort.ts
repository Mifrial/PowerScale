import { registerCharacterSessionRuntimePort } from '@/modules/Roleplay/Character/init';
import type { ICharacterSessionRuntimePort } from '@/modules/Roleplay/Character/Interface/ICharacterSessionRuntimePort';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import {
  gameCharacterMemberships,
  isSessionActive,
  syncCharacterVersionToMemberships,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import {
  getStoredCharacterVersion,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { MockGameCharacterRuntimeMutationPort } from '@/modules/Roleplay/Game/Mock/MockGameCharacterRuntimeMutationPort';

const runtimePort: ICharacterSessionRuntimePort = {
  sessionTarget(characterId, gameId) {
    const candidates = gameCharacterMemberships.filter(
      (membership) => membership.characterId === characterId && membership.membershipStatus === 'active',
    );
    const target =
      gameId !== undefined
        ? (candidates.find((membership) => membership.gameId === gameId) ?? null)
        : (candidates.find((membership) => isSessionActive(membership.gameId)) ?? null);
    if (!target || !isSessionActive(target.gameId)) return null;

    return {
      gameId: target.gameId,
      characterId: target.characterId,
      approvedCharacterVersion: target.approvedCharacterVersion,
    };
  },
  async applyActualPatch(gameId, characterId, patch: CharacterPatch) {
    if (!isSessionActive(gameId)) throw new Error('Сессия игры не активна');
    new MockGameCharacterRuntimeMutationPort().applyPatch(characterId, patch);
  },
  readActual(_gameId, characterId) {
    return getStoredCharacterVersion(characterId);
  },
  syncLatestToMemberships(characterId) {
    syncCharacterVersionToMemberships(characterId);
  },
};

export function registerMockCharacterSessionRuntimePort(): void {
  registerCharacterSessionRuntimePort(runtimePort);
}

registerMockCharacterSessionRuntimePort();
