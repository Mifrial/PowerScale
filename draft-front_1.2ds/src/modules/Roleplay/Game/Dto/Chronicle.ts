import type { ChronicleEpoch } from '@/modules/Roleplay/Game/Enum/ChronicleEpoch';

/**
 * Летопись игры (ТР §3 `chronicles`): ровно один владелец — игра (`gameId`), создаётся лениво
 * при первом обращении (`game.getChronicle`). `epoch` — точка отсчёта сдвигов записей
 * (сейчас единственное значение «от начала приключения»).
 */
export interface Chronicle {
  id: number;
  gameId: number;
  name: string | null;
  epoch: ChronicleEpoch;
}
