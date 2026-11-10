import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';

/** Вложение одного поля в другое. */
export interface SceneEnclosure {
  id: number;
  parentBoardId: number;
  childBoardId: number;
  footprint: SceneShape;
  sortOrder: number;
}
