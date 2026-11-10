import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';

/** Элемент в снимке сохранения. */
export interface SceneSaveElement {
  id: number;
  boardId: number;
  kind: SceneElementKind;
  shape: SceneShape;
  props: SceneElementProps;
  sortOrder: number;
  targetBoardId: number | null;
}
