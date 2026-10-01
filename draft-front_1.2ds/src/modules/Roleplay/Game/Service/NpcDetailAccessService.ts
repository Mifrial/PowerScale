import type { User } from '@/modules/Core/User/Dto/User';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';

interface GameAccessPort {
  canViewGame(user: User | null | undefined, game: Game, memberIds: number[]): boolean;
  canEditGame(user: User | null | undefined, detail: GameDetail): boolean;
}

interface SheetAccessPort {
  canSeeSheet(user: User | null | undefined, visibility: SheetVisibility, ctx: SheetAccessContext): boolean;
}

/**
 * Доступ к полной деталке NPC: сначала игра, затем зоны листа.
 * Предложение видят только автор и тот, кто может редактировать игру.
 */
export class NpcDetailAccessService {
  constructor(
    private readonly gameAccess: GameAccessPort,
    private readonly sheetAccess: SheetAccessPort,
  ) {}

  canOpen(user: User | null | undefined, detail: GameDetail, npc: GameNpc): boolean {
    if (!user) return false;
    const memberIds = detail.members.map((member) => member.userId);
    if (!this.gameAccess.canViewGame(user, detail.game, memberIds)) return false;
    if (
      !this.sheetAccess.canSeeSheet(user, npc.visibility, {
        user,
        ownerId: null,
        characterId: npc.id,
        gameId: npc.gameId,
      })
    ) {
      return false;
    }
    if (npc.status !== 'proposed') return true;

    return npc.proposedBy?.userId === user.id || this.gameAccess.canEditGame(user, detail);
  }

  showsFullSheet(version: CharacterVersion | null, sections: readonly SheetSection[]): boolean {
    return version !== null && SHEET_VISIBLE_SECTIONS.every((section) => sections.includes(section));
  }
}
