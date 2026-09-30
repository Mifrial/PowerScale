import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameMembershipReviewState } from '@/modules/Roleplay/Game/Enum/GameMembershipReviewState';
import { characterDiffService } from '@/modules/Roleplay/Character/init';

/** Вычисляет состояние проверки персонажа перед и во время игровой сессии. */
export class GameMembershipReviewService {
  needsModeration(approved: CharacterVersion | null, actual: CharacterVersion | null): boolean {
    const diff = characterDiffService.getCharacterDiff(approved, actual);

    return diff.availability !== 'complete' || diff.hasChanges;
  }

  reviewState(input: {
    returned: boolean;
    approved: CharacterVersion | null;
    actual: CharacterVersion | null;
  }): GameMembershipReviewState {
    if (input.returned) return 'returned';
    if (this.needsModeration(input.approved, input.actual)) return 'changes_pending';

    return 'clean';
  }
}
