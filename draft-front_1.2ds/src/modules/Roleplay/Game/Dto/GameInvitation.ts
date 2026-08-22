import type { GameInvitationStatus } from '@/modules/Roleplay/Game/Enum/GameInvitationStatus';

/** Приглашение в игру (ТР §3 `game_invitations`). */
export interface GameInvitation {
  id: number;
  gameId: number;
  inviterId: number;
  inviterName: string;
  inviteeId: number;
  inviteeName: string;
  status: GameInvitationStatus;
  createdAt: string;
}
