import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';

/**
 * Участник игры (ТР §3 `game_members` + индивидуальные права `game_member_permissions`).
 * `permissions` — индивидуальные per-game права участника поверх роли (game.edit, game.moderate, ...).
 */
export interface GameMember {
  userId: number;
  userName: string;
  role: GameMemberRole;
  permissions: string[];
}
