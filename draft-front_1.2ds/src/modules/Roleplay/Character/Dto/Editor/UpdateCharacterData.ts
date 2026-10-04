import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';

/**
 * Данные обновления персонажа: собранная версия (copy-on-write — черновик подменяет оригинал).
 * `status` — статус ЛИСТА, решает вызывающий контекст (редактор вне игры — 'ready').
 * `gameId` — контекст игры (in-game редактор): при запущенной текущей сессии (approved + sessionRunning) роутер
 * пишет изменение в actual runtime, иначе — в latest + автоподача (модель версий — Баг 1).
 * @deprecated Compatibility DTO for the pre-patch editor path. New Character API uses CharacterPatch.
 */
export interface UpdateCharacterData {
  version: CharacterVersion;
  status?: CharacterStatus;
  gameId?: number;
}
