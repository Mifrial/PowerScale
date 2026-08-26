import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

export interface CharacterDetail {
  character: Character;
  version: CharacterVersion;
  /** Дубль `character.discussionChatId` для вкладок карточки; источник истины — поле списка. */
  discussionChatId: number | null;
  /** Предыдущая версия (до последней миграции правил) — для сравнения «до/после». */
  previousVersion: CharacterVersion | null;
  /**
   * Личные заметки владельца. Есть только если текущий пользователь — владелец;
   * не входят в CharacterVersion (модерация/ГМ их не видят).
   */
  ownerNotes?: string | null;
}
