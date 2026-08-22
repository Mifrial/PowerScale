import type { GameTime } from '@/modules/Roleplay/Game/Dto/GameTime';
import type { ChronicleRef } from '@/modules/Roleplay/Game/Dto/ChronicleRef';

/**
 * Запись летописи (ТР §3 `chronicle_entries`). Позиция в хронике — по `offset` (сдвиг от
 * точки отсчёта, `gameTimeToSeconds`), а не ручной `sort_order` — осознанное расхождение
 * с ТР §8 (зафиксировано в спека §7.16, D82+). Ссылки на персонажей/НПС — инлайн-токенами
 * `[[character:id]]`/`[[npc:id]]` в `content` (как в чате); `related` — производное от них,
 * заполняется на границе «бэка». Записи создаёт ведущий, игроки видят read-only.
 */
export interface ChronicleEntry {
  id: number;
  chronicleId: number;
  title: string;
  content: string;
  /** Нормализованный сдвиг (канонические максимальные единицы). */
  offset: GameTime;
  related: ChronicleRef[];
  createdBy: number;
  createdAt: string;
  updatedAt: string;
}
