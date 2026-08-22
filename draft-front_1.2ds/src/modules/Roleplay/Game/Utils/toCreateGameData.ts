import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';

/** Полная игра → данные создания/обновления (для game.update из карточки: статус и т.д.). */
export function toCreateGameData(detail: GameDetail): CreateGameData {
  return {
    name: detail.game.name,
    shortDescription: detail.game.shortDescription,
    description: detail.description,
    spaceId: detail.game.spaceId,
    spaceCode: detail.game.spaceCode,
    rulesRevision: detail.game.rulesRevision,
    status: detail.game.status,
    visibility: detail.game.visibility,
    joinPolicy: detail.game.joinPolicy,
    osPointsLimit: detail.osPointsLimit,
    olPointsLimit: detail.olPointsLimit,
    orPointsLimit: detail.orPointsLimit,
    moneyLimit: detail.moneyLimit,
    tags: detail.game.tags,
    forbiddenTags: detail.forbiddenTags,
  };
}
