import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';

/** Описание инструмента: что создаёт и с какими свойствами. */
export interface SceneToolDefinition {
  id: SceneTool;
  label: string;
  icon: string;
  /** Тип создаваемого элемента; null у выбора. */
  kind: SceneElementKind | null;
  /** Тип геометрии; null у выбора. */
  shapeType: 'rect' | 'circle' | 'wall' | 'point' | 'polygon' | null;
  props: SceneElementProps;
}
