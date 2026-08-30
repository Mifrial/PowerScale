import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameMembershipReviewState } from '@/modules/Roleplay/Game/Enum/GameMembershipReviewState';
import { sessionCharacterService } from '@/modules/Roleplay/Game/Service/Instance/sessionCharacterService';

export class GameMembershipReviewService {
  reviewState(input: {
    returned: boolean;
    approved: CharacterVersion | null;
    actual: CharacterVersion | null;
  }): GameMembershipReviewState {
    if (input.returned) return 'returned';
    if (sessionCharacterService.needsModeration(input.approved, input.actual)) return 'changes_pending';

    return 'clean';
  }
}
