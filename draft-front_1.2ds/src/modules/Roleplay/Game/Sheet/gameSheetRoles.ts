import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import { registerSheetRole } from '@/modules/Roleplay/Character/init';
import { accessService } from '@/modules/Core/User/init';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import { gameCharacterMemberships } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';

/**
 * Инъекция роли «ведущие» в общую модель видимости листа (Character).
 * `fullAccess: true` — ведущие контекста видят лист полностью, владелец не может это отменить.
 * Резолв: в игровом контексте — роль участника игры (owner/gm) или глобальное `game.edit_all`;
 * на standalone — ведущий любой игры, где персонаж участвует.
 */
export function registerGameSheetRoles(): void {
  registerSheetRole({
    name: 'gm',
    fullAccess: true,
    resolve: (ctx) => isGm(ctx),
  });
}

function isGm(ctx: SheetAccessContext): boolean {
  if (accessService.hasAnyPermission(ctx.user, ['game.edit_all'])) return true;

  const rolesInGame = (gameId: number): boolean => {
    const detail = gameDetails.find((detail) => detail.game.id === gameId);
    const member = detail?.members.find((member) => member.userId === ctx.user.id);

    return member?.role === 'owner' || member?.role === 'gm';
  };

  if (ctx.gameId !== null) return rolesInGame(ctx.gameId);

  const memberships = gameCharacterMemberships.filter((membership) => membership.characterId === ctx.characterId);

  return memberships.some((membership) => rolesInGame(membership.gameId));
}
