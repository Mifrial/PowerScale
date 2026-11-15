/** Детали конфликта версий мира или статьи. */
export interface LoreConflict {
  currentVersion: number;
  updatedAt: number;
  updatedBy: number | null;
}
