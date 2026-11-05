/** Вариант выбора в окне удара. */
export interface GamePhpStrikeAwaitingOption {
  key?: string;
  actor?: { type: string; id: number };
  check?: string;
  characteristic?: string;
}

/** Ожидание решения по открытому удару: выбор фактической цели, раскрытие манёвра. */
export interface GamePhpStrikeAwaiting {
  kind: 'actual_target' | 'maneuver_reveal' | string;
  /** Фаза окна манёвра: выбор проверки или отказ. */
  phase?: 'check' | 'decision' | string;
  actor: { type: string; id: number };
  options: GamePhpStrikeAwaitingOption[];
  /** Бросок защитника на фазе отказа. */
  roll?: Record<string, unknown> | null;
  won?: boolean | null;
}
