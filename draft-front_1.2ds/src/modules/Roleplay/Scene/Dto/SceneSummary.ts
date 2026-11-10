/** Краткая локация для списка. */
export interface SceneSummary {
  id: number;
  ownerId: number;
  name: string;
  description: string;
  resolution: number;
  schemaVersion: number;
  isArchived: boolean;
  version: number;
  updatedBy: number | null;
  createdAt: number;
  updatedAt: number;
}
