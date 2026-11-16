import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';

/** Вложение одного поля в другое. */
export interface SceneEnclosure {
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
