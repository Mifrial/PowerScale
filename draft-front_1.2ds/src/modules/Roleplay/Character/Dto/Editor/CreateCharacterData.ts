import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';

/**
 * Данные создания персонажа (свободное создание): привязка к ревизии + собранная версия.
 * `status` — статус ЛИСТА (draft|ready), решает вызывающий контекст. Модуль персонажа не знает об играх.
 */
export interface CreateCharacterData {
  spaceId: number;
  spaceCode: string;
  rulesRevision: number;
  version: CharacterVersion;
  status?: CharacterStatus;
}
