export type DateTimeFilterValue =
  | { mode: 'equals'; value: string }
  | { mode: 'from'; from: string }
  | { mode: 'to'; to: string }
  | { mode: 'interval'; from?: string; to?: string };
