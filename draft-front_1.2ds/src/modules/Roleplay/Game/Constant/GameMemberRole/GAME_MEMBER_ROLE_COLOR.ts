import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';

export const GAME_MEMBER_ROLE_COLOR: Record<GameMemberRole, string> = {
  owner: 'primary',
  gm: 'info',
  player: 'default',
};
