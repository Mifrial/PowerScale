import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';

/** Вложение в снимке сохранения. */
export interface SceneSaveEnclosure {
  id: number;
  parentBoardId: number;
  childBoardId: number;
  footprint: SceneShape;
  sortOrder: number;
}
