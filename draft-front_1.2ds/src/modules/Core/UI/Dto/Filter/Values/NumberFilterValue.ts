export type NumberFilterValue =
  | { mode: 'equals'; value: number }
  | { mode: 'from'; from: number }
  | { mode: 'to'; to: number }
  | { mode: 'interval'; from: number; to: number };
