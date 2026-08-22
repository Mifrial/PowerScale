import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';

export interface GameMemberRoleOption {
  value: GameMemberRole;
  label: string;
}

export const GAME_MEMBER_ROLE_OPTIONS: GameMemberRoleOption[] = [
  { value: 'owner', label: 'Владелец' },
  { value: 'gm', label: 'Ведущий' },
  { value: 'player', label: 'Участник' },
];
