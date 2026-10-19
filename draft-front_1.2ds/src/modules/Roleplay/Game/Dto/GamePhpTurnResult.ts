/** Ответ конца хода / continue / abort / woundAction. */
export interface GamePhpTurnResult {
  version: number;
  battleId?: number;
  sheetVersion?: number;
  activeIndex?: number;
  round?: number;
  /** `continueAction` открыл отложенный удар. */
  opened?: boolean;
  /** Почему отложенный удар не открылся. */
  refused?: string;
  /** Остаток ОД в долге, если удар ещё не открыт. */
  remaining?: number;
}
