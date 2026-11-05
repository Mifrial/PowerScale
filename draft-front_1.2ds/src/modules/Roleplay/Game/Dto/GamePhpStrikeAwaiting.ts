/** Ожидание решения по открытому удару: выбор фактической цели и подобное. */
export interface GamePhpStrikeAwaiting {
  kind: 'actual_target' | string;
  actor: { type: string; id: number };
  options: { key: string; actor: { type: string; id: number } }[];
}
