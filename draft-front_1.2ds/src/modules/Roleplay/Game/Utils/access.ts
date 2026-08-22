import { accessService } from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';
import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';

function memberOf(user: User | null | undefined, members: GameMember[]): GameMember | null {
  if (!user) return null;

  return members.find((m) => m.userId === user.id) ?? null;
}

/**
 * Право просмотра игры (ТР §8): владелец всегда; `game.view_all` — все, включая черновики;
 * черновик виден только владельцу и `game.view_all`; видимость 'players'/'invited'/'whitelist' —
 * только участникам. 'friends' без модели дружбы трактуется как «все» (упрощение прототипа).
 */
export function canViewGame(user: User | null | undefined, game: Game, memberIds: number[]): boolean {
  if (!user) return false;
  if (user.id === game.ownerId) return true;
  if (accessService.hasAnyPermission(user, ['game.view_all'])) return true;
  if (game.status === 'draft') return false;
  if (game.visibility === 'all' || game.visibility === 'friends') return true;

  return memberIds.includes(user.id);
}

/**
 * Право редактирования настроек игры (ТР §4): владелец/ведущий или индивидуальное
 * per-game право `game.edit` (`game_member_permissions`).
 */
export function canEditGame(user: User | null | undefined, detail: GameDetail): boolean {
  if (!user) return false;
  if (accessService.hasAnyPermission(user, ['game.edit_all'])) return true;
  const member = memberOf(user, detail.members);
  if (!member) return false;
  if (member.role === 'owner' || member.role === 'gm') return true;

  return member.permissions.includes('game.edit');
}

/**
 * Право модерации (панель ведущего): владелец/ведущий или индивидуальное
 * per-game право `game.moderate`.
 */
export function canModerateGame(user: User | null | undefined, detail: GameDetail): boolean {
  if (!user) return false;
  if (accessService.hasAnyPermission(user, ['game.edit_all'])) return true;
  const member = memberOf(user, detail.members);
  if (!member) return false;
  if (member.role === 'owner' || member.role === 'gm') return true;

  return member.permissions.includes('game.moderate');
}

/**
 * Право напрямую добавлять участника (без приглашения): только владелец игры или
 * глобальный админ (`game.edit_all`). Ведущие добавляют по приглашению.
 */
export function canAddGameMember(user: User | null | undefined, detail: GameDetail): boolean {
  if (!user) return false;
  if (accessService.hasAnyPermission(user, ['game.edit_all'])) return true;
  const member = memberOf(user, detail.members);
  if (!member) return false;

  return member.role === 'owner';
}
