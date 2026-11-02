/** Изменение счётчика на успехе или провале шага: прибавка или установка. */
export type CounterOp = { counter_code: string; delta: number } | { counter_code: string; set: number };
