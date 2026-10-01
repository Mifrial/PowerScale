import type { User } from '@/modules/Core/User/Dto/User';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import { sheetAccessService } from '@/modules/Roleplay/Character/Service/Instance/sheetAccessService';

/**
 * Право просмотра листа персонажа (зоны видимости «вообще», ТР §7): владелец всегда;
 * иначе — по `canSeeSheet` в standalone-контексте (аудитория 'all' = character.view,
 * роль 'gm' — через инъекцию, number[] — выбранные). Нет доступа → персонаж невидим.
 */
export class CharacterAccessService {
  constructor(private readonly sheetAccess = sheetAccessService) {}
  canViewCharacter(user: User | null | undefined, character: Character): boolean {
    return this.canViewStandaloneSheet(user, {
      id: character.id,
      ownerId: character.ownerId,
      visibility: character.visibility,
    });
  }

  /** Просмотр листа в standalone-контексте (`gameId: null`), без полного `Character`. */
  canViewStandaloneSheet(
    user: User | null | undefined,
    sheet: { id: number; ownerId: number; visibility: SheetVisibility },
  ): boolean {
    if (!user) return false;
    if (user.id === sheet.ownerId) return true;
    const ctx: SheetAccessContext = {
      user,
      ownerId: sheet.ownerId,
      characterId: sheet.id,
      gameId: null,
    };

    return this.sheetAccess.canSeeSheet(user, sheet.visibility, ctx);
  }

  /** Право редактирования: только владелец (ТР §7 — редактирование без чужого подтверждения вне игры). */
  canEditCharacter(user: User | null | undefined, character: Character): boolean {
    if (!user) return false;

    return user.id === character.ownerId;
  }
}
