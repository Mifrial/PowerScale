import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';

interface GameCharacterModerationConflict {
  code: 'stale_actual_version' | 'stale_membership_revision' | 'command_fingerprint_conflict';
  currentActualVersion: number;
  currentMembershipRevision: number;
  retriable: boolean;
}

export type GameCharacterModerationResult =
  | {
      kind: 'transition';
      commandId: string;
      status: 'approved' | 'returned' | 'rejected' | 'already_applied';
      membership: GameCharacterMembership;
    }
  | {
      kind: 'conflict';
      commandId: string;
      status: 'rejected';
      conflict: GameCharacterModerationConflict;
    };
