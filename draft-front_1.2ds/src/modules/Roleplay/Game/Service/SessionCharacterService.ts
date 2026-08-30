import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
import { isEmptyMembershipDiff, membershipDiff } from '@/modules/Roleplay/Game/Utils/membershipDiff';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

export class SessionCharacterService {
  resolve(approved: CharacterVersion | null, overlay: GameCombatOverlay | null): CharacterVersion | null {
    if (approved === null) {
      return overlay?.sheet ? this.stripIdentity(overlay.sheet, null) : null;
    }
    const base = overlay?.sheet ? this.stripIdentity(overlay.sheet, approved) : cloneData(approved);
    if (!overlay || overlay.updatedAt === '') return base;

    return combatOverlayService.mergeCombatOverlay(base, overlay);
  }

  stripIdentity(sheet: CharacterVersion, approved: CharacterVersion | null): CharacterVersion {
    const copy = cloneData(sheet);
    if (approved === null) return copy;
    copy.spaceCode = approved.spaceCode;
    copy.rulesRevision = approved.rulesRevision;

    return copy;
  }

  needsModeration(approved: CharacterVersion | null, actual: CharacterVersion | null): boolean {
    if (approved === null || actual === null) return true;

    return !isEmptyMembershipDiff(membershipDiff(approved, actual));
  }

  isEligibleForSession(input: {
    membershipStatus: GameMembershipStatus;
    approved: CharacterVersion | null;
    actual: CharacterVersion | null;
    gameRulesRevision: number;
    needsFix: boolean;
  }): boolean {
    if (input.membershipStatus !== 'active') return false;
    if (input.needsFix) return false;
    if (input.actual === null) return false;
    if (input.actual.rulesRevision !== input.gameRulesRevision) return false;
    if (this.needsModeration(input.approved, input.actual)) return false;

    return true;
  }
}
