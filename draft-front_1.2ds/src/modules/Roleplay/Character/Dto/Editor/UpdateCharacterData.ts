import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';

/**
 * Данные обновления персонажа: собранная версия (copy-on-write — черновик подменяет оригинал).
 * `status` — статус ЛИСТА, решает вызывающий контекст (редактор вне игры — 'ready').
 * `gameId` — контекст игры (in-game редактор): при активной сессии (approved + playing) роутер
 * пишет изменение в сессионный оверлей, иначе — в latest + автоподача (модель версий — Баг 1).
 */
export interface UpdateCharacterData {
  version: CharacterVersion;
  status?: CharacterStatus;
  gameId?: number;
}
