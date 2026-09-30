import type { CharacterDiff } from '@/modules/Roleplay/Character/Dto/CharacterDiff';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameMembershipReviewState } from '@/modules/Roleplay/Game/Enum/GameMembershipReviewState';

export interface CharacterModerationProjection {
  characterId: number;
  approvedCharacterVersion: CharacterVersion | null;
  actualCharacterVersion: CharacterVersion | null;
  actualVersion: number | null;
  diff: CharacterDiff | null;
  reviewState: GameMembershipReviewState;
}
