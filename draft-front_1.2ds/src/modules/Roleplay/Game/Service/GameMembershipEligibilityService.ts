import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';
import { characterDiffService } from '@/modules/Roleplay/Character/init';

/** Проверяет, может ли членство персонажа участвовать в игровой сессии. */
export class GameMembershipEligibilityService {
  canStartSession(input: {
    membershipStatus: GameMembershipStatus;
    returned: boolean;
    approved: CharacterVersion | null;
    actual: CharacterVersion | null;
    gameSpaceCode: string;
    gameRulesRevision: number;
    needsFix: boolean;
  }): boolean {
    if (input.membershipStatus !== 'active') return false;
    if (input.returned || input.needsFix) return false;
    if (input.approved === null || input.actual === null) return false;
    if (input.approved.spaceCode !== input.gameSpaceCode || input.actual.spaceCode !== input.gameSpaceCode)
      return false;
    if (
      input.approved.rulesRevision !== input.gameRulesRevision ||
      input.actual.rulesRevision !== input.gameRulesRevision
    )
      return false;

    const diff = characterDiffService.getCharacterDiff(input.approved, input.actual);

    return diff.availability === 'complete' && diff.identity.compatible && !diff.hasChanges;
  }

  isActiveSessionParticipant(input: {
    membershipStatus: GameMembershipStatus;
    sessionParticipant: boolean;
    returned: boolean;
  }): boolean {
    return input.membershipStatus === 'active' && input.sessionParticipant && !input.returned;
  }
}
