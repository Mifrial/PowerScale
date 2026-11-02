/** Формула процесса: фиксированное число или значение от счётчика сессии. */
export type ProcessFormula =
  | { type: 'fixed'; value: number }
  | {
      type: 'counter';
      counter_code: string;
      factor?: number;
      divisor?: number;
      min_value?: number;
      max_value?: number;
    };
