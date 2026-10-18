/** Ответ конца хода / continue / abort / woundAction. */
export interface GamePhpTurnResult {
  version: number;
  battleId?: number;
  sheetVersion?: number;
  activeIndex?: number;
  round?: number;
}
