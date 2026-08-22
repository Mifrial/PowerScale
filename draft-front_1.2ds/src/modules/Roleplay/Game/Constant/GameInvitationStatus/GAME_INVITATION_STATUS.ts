import type { GameInvitationStatus } from '@/modules/Roleplay/Game/Enum/GameInvitationStatus';

export const GAME_INVITATION_STATUS_LABEL: Record<GameInvitationStatus, string> = {
  sent: 'Отправлено',
  viewed: 'Просмотрено',
  accepted: 'Принято',
  declined: 'Отклонено',
};

export const GAME_INVITATION_STATUS_COLOR: Record<GameInvitationStatus, string> = {
  sent: 'info',
  viewed: 'warning',
  accepted: 'success',
  declined: 'grey',
};
