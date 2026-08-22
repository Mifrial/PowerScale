import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';

export const GAME_MEMBERSHIP_STATUS_LABEL: Record<GameMembershipStatus, string> = {
  pending: 'На модерации',
  approved: 'В игре',
  rejected: 'Отклонено',
  left: 'Покинул игру',
};

export const GAME_MEMBERSHIP_STATUS_COLOR: Record<GameMembershipStatus, string> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'error',
  left: 'grey',
};
