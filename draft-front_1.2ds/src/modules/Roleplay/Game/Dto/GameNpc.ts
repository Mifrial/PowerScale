import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameNpcStatus } from '@/modules/Roleplay/Game/Enum/GameNpcStatus';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';

/**
 * НПС игры (ТР §8). По сути — персонаж игры без владельца-игрока: полный лист
 * `version` (CharacterVersion, как у персонажей; null — заполнен только минимум).
 * Игроки видят НПС по `visibility`, предложенные игроками — статус 'proposed'.
 */
export interface GameNpc {
  id: number;
  gameId: number;
  name: string;
  shortDescription: string | null;
  fullDescription: string | null;
  /** Описательные теги НПС (роль/тип: торговец, наёмник...) — для поиска по списку НПС. */
  tags: string[];
  version: CharacterVersion | null;
  status: GameNpcStatus;
  proposedBy: { userId: number; userName: string } | null;
  visibility: SheetVisibility;
  updatedAt: string;
}
