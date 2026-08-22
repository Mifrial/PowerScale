import type { GameTime } from '@/modules/Roleplay/Game/Dto/GameTime';

/** Данные редактирования записи летописи (ведущий). Ссылки — инлайн-токенами в `content`. */
export interface UpdateChronicleEntryData {
  title: string;
  content: string;
  offset: GameTime;
}
