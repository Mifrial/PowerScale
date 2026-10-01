import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';
import { characterDiffService } from '@/modules/Roleplay/Character/init';
import { gameMembershipReviewService } from '@/modules/Roleplay/Game/Service/Instance/gameMembershipReviewService';

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

  /** Лист ждёт модерации: approved и actual не совпали. */
  sheetNeedsModeration(approved: CharacterVersion | null, actual: CharacterVersion | null): boolean {
    return gameMembershipReviewService.needsModeration(approved, actual);
  }

  /** Персонаж может говорить в чате от своего лица. */
  canSpeakAsCharacter(input: {
    membershipStatus: GameMembershipStatus;
    characterOwnerId: number;
    currentUserId: number;
    approved: CharacterVersion | null;
    actual: CharacterVersion | null;
  }): boolean {
    return (
      input.membershipStatus === 'active' &&
      input.characterOwnerId === input.currentUserId &&
      !this.sheetNeedsModeration(input.approved, input.actual)
    );
  }

  isActiveSessionParticipant(input: {
    membershipStatus: GameMembershipStatus;
    sessionParticipant: boolean;
    returned: boolean;
  }): boolean {
    return input.membershipStatus === 'active' && input.sessionParticipant && !input.returned;
  }
}
