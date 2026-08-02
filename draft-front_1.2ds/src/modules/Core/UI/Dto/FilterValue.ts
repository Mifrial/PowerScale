export interface StringFilterValue {
  mode: 'equals' | 'contains'
  value: string
}

export type NumberFilterValue =
  | { mode: 'equals'; value: number }
  | { mode: 'from'; from: number }
  | { mode: 'to'; to: number }
  | { mode: 'interval'; from: number; to: number }

export type DateTimeFilterValue =
  | { mode: 'equals'; value: string }
  | { mode: 'from'; from: string }
  | { mode: 'to'; to: string }
  | { mode: 'interval'; from?: string; to?: string }

export type FilterOptionValue = string | number | boolean

export type FilterValue = boolean | string | number | StringFilterValue | NumberFilterValue | DateTimeFilterValue
