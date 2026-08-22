/** Состояние очков персонажа: ОС/ОЛ/ОР. */
export interface CharacterPointsState {
  /** ОС — очки создания: сколько потрачено при создании. */
  osSpent: number;
  /** ОЛ — очки личности. */
  olSpent: number;
  olTotal: number;
  /** ОР — очки развития. */
  orSpent: number;
  /** Итог ОР; null — лимит не задан (персонаж создан без лимита ОР). */
  orTotal: number | null;
}
