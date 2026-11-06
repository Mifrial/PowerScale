/** Детали конфликта версий карты. */
export interface MapConflict {
  currentVersion: number;
  updatedAt: number;
  updatedBy: number | null;
}
