import type { SceneSaveSnapshot } from '@/modules/Roleplay/Scene/Dto/SceneSaveSnapshot';

/** Вход `scene.save`. */
export interface SaveSceneData {
  id?: number;
  expectedVersion?: number;
  scene: SceneSaveSnapshot;
}
