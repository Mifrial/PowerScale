import type { GameTime } from '@/modules/Roleplay/Game/Dto/GameTime';

/**
 * Данные создания записи летописи (ведущий). Ссылки на персонажей/НПС вставляются
 * инлайн-токенами `[[character:id]]`/`[[npc:id]]` в `content` (как в чате) — `related`
 * производятся из них на границе «бэка». Сдвиг нормализуется в моке при сохранении.
 */
export interface CreateChronicleEntryData {
  title: string;
  content: string;
  offset: GameTime;
}
