import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';
import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';
import type { GameMembershipReviewState } from '@/modules/Roleplay/Game/Enum/GameMembershipReviewState';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';

/**
 * Членство персонажа в игре. Персонаж — не более чем в одной игре (кроме `left`).
 * `approvedCharacterVersion` — immutable-копия actual на момент approve.
 * Сессия: `resolve(approvedCharacterVersion, overlay)`.
 * Модерация: approved == null или diff(approved, actual); `reviewState` кроме `returned` вычисляется.
 */
export interface GameCharacterMembership {
  gameId: number;
  characterId: number;
  characterName: string;
  characterOwnerId: number;
  characterOwnerName: string;
  role: GameMemberRole;
  membershipStatus: GameMembershipStatus;
  approvedCharacterVersion: CharacterVersion | null;
  reviewState: GameMembershipReviewState;
  returnedAt: string | null;
  returnReason: string | null;
  returnMessageId: number | null;
  overlay: GameCombatOverlay | null;
  visibility: SheetVisibility;
  osBonus: number;
  orBonus: number;
  olBonus: number;
  updatedAt: string;
}
