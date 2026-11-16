import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';

/** Вложение в снимке сохранения. */
export interface SceneSaveEnclosure {
  id: number;
  parentBoardId: number;
  childBoardId: number;
  footprint: SceneShape;
  /** Вертикальная отметка относительно родителя, единицы сцены. */
  elevation: number;
  /** Имя строения или этажа; null — берётся имя поля. */
  label: string | null;
  sortOrder: number;
}
