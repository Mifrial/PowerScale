import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';

export const GAME_MEMBERSHIP_STATUS_LABEL: Record<GameMembershipStatus, string> = {
  submitted: 'Заявка',
  active: 'В игре',
  left: 'Покинул игру',
};

export const GAME_MEMBERSHIP_STATUS_COLOR: Record<GameMembershipStatus, string> = {
  submitted: 'warning',
  active: 'success',
  left: 'grey',
};
