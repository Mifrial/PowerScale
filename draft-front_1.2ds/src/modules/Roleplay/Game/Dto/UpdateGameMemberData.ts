import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';

/** Обновление участника: роль + индивидуальные per-game права (ТР §3 `game_member_permissions`). */
export interface UpdateGameMemberData {
  role: GameMemberRole;
  permissions: string[];
}
