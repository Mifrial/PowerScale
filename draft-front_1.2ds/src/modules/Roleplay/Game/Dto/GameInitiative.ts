/** Тип участника шкалы инициативы (персонаж игрока или НПС игры). */
export type GameInitiativeParticipantKind = 'character' | 'npc';

/**
 * Участник шкалы инициативы (ТР §8 «Чат игры»). Результат броска используется только для
 * определения порядка в момент броска и не хранится: `participants` несёт готовый порядок хода.
 */
export interface GameInitiativeParticipant {
  id: string;
  name: string;
  kind: GameInitiativeParticipantKind;
  entityId: number | null;
}

/**
 * Шкала инициативы игры: `participants` — порядок хода (сортировка по результату броска
 * + случайный тай-брейк, заморожен; добавленные в бой — в конце), `activeIndex` — текущий ход
 * (null — ход не начат). `active` — шкала открыта («Закончить» скрывает, данные сохраняются).
 * `round` — номер раунда: 1 со старта, инкремент при переходе от последнего к первому участнику.
 */
export interface GameInitiative {
  gameId: number;
  active: boolean;
  participants: GameInitiativeParticipant[];
  activeIndex: number | null;
  round: number;
  updatedAt: string;
}
