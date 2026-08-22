import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { GameVisibility } from '@/modules/Roleplay/Game/Enum/GameVisibility';
import type { GameJoinPolicy } from '@/modules/Roleplay/Game/Enum/GameJoinPolicy';

/** Данные создания/обновления игры (ТР §8: статус, видимость, join-policy независимы; лимиты null = без лимита). */
export interface CreateGameData {
  name: string;
  shortDescription: string | null;
  description: string | null;
  spaceId: number;
  spaceCode: string;
  rulesRevision: number;
  status: GameStatus;
  visibility: GameVisibility;
  joinPolicy: GameJoinPolicy;
  osPointsLimit: number | null;
  olPointsLimit: number | null;
  orPointsLimit: number | null;
  moneyLimit: number | null;
  tags: string[];
  forbiddenTags: string[];
}
