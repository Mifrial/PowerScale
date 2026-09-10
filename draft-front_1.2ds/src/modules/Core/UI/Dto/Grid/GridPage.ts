export interface GridPage<T extends Record<string, unknown>> {
  rows: T[];
  total: number;
}
