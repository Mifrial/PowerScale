/** Бюджет редактора: потрачено / всего. */
export interface EditorBudget {
  total: number | null;
  spent: number;
  /** Лимит превышен (только при заданном total). */
  exceeded: boolean;
}
