import { registerCharacterSessionOverlay } from '@/modules/Roleplay/Character/init';
import type { ICharacterSessionOverlay } from '@/modules/Roleplay/Character/Interface/ICharacterSessionOverlay';
import {
  gameCharacterMemberships,
  isSessionActive,
  syncCharacterVersionToMemberships,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import {
  combatKey,
  getStoredCombatOverlay,
  writeOverlaySheet,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';

const overlay: ICharacterSessionOverlay = {
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
  async writeSheet(gameId, characterId, version) {
    await writeOverlaySheet(gameId, combatKey('character', characterId), version);
  },
  readSheet(gameId, characterId) {
    return getStoredCombatOverlay(gameId, combatKey('character', characterId))?.sheet ?? null;
  },
  syncLatestToMemberships(characterId) {
    syncCharacterVersionToMemberships(characterId);
  },
};

export function registerMockCharacterSessionOverlay(): void {
  registerCharacterSessionOverlay(overlay);
}

registerMockCharacterSessionOverlay();
