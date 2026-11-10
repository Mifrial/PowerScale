/** Данные конфликта версий из `SCENE_VERSION_CONFLICT`. */
export interface SceneConflict {
  currentVersion: number;
  updatedAt: number;
  updatedBy: number | null;
}
