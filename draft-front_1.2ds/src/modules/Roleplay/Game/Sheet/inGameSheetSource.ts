import type { IInGameSheetSource } from '@/modules/Roleplay/Character/Interface/IInGameSheetSource';
import { sessionCharacterService } from '@/modules/Roleplay/Game/Service/Instance/sessionCharacterService';

export const inGameSheetSource: IInGameSheetSource = {
  getEffectiveSheet: async (gameId, characterId, signal) => {
    const { getGameApi } = await import('@/modules/Roleplay/Game/init');
    const memberships = await getGameApi().getGameCharacters(gameId, signal);
    const membership = memberships.find((entry) => entry.characterId === characterId);
    if (!membership) return null;

    return sessionCharacterService.resolve(membership.approvedCharacterVersion, membership.overlay);
  },
};
