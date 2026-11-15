import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';

/** Элемент поля. */
export interface SceneElement {
  id: number;
  boardId: number;
  kind: SceneElementKind;
  shape: SceneShape;
  props: SceneElementProps;
  sortOrder: number;
  targetBoardId: number | null;
  asset: { publicId: string; contentBox: ImageContentBox | null } | null;
}
