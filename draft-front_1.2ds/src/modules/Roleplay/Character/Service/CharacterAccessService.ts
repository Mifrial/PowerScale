import type { User } from '@/modules/Core/User/Dto/User';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import { sheetAccessService } from '@/modules/Roleplay/Character/Service/Instance/sheetAccessService';

/**
 * Право просмотра листа персонажа (зоны видимости «вообще», ТР §7): владелец всегда;
 * иначе — по `canSeeSheet` в standalone-контексте (аудитория 'all' = character.view,
 * роль 'gm' — через инъекцию, number[] — выбранные). Нет доступа → персонаж невидим.
 */
export class CharacterAccessService {
  canViewCharacter(user: User | null | undefined, character: Character): boolean {
    if (!user) return false;
    if (user.id === character.ownerId) return true;
    const ctx: SheetAccessContext = {
      user,
      ownerId: character.ownerId,
      characterId: character.id,
      gameId: null,
    };

    return sheetAccessService.canSeeSheet(user, character.visibility, ctx);
  }

  /** Право редактирования: только владелец (ТР §7 — редактирование без чужого подтверждения вне игры). */
  canEditCharacter(user: User | null | undefined, character: Character): boolean {
    if (!user) return false;

    return user.id === character.ownerId;
  }
}
