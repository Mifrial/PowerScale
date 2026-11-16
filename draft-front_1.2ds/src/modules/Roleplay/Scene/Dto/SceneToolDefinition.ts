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
  shapeType: 'rect' | 'circle' | 'wall' | 'point' | 'polygon' | 'path' | null;
  props: SceneElementProps;
  /** Группа инструментов с общей кнопкой и меню вариантов. */
  group?: string;
  /** Подпись варианта внутри группы. */
  variant?: string;
  /** Ширина ленты в ипари для пресета. */
  widthIpari?: number;
  /** Ширина кромки в ипари для пресета. */
  shoreWidthIpari?: number;
  /** Глубина воды в ипари для пресета. */
  depthIpari?: number;
  /** Толщина стены в ипари для пресета. */
  wallWidthIpari?: number;
  /** Форма стены: обычная лента или замкнутое кольцо. */
  wallMode?: 'line' | 'ring';
  /** Ширина проёма в ипари для пресета. */
  openingWidthIpari?: number;
  /** Высота стены в ипари для пресета. */
  heightIpari?: number;
  /** Подоконник окна в ипари для пресета. */
  sillIpari?: number;
  /** Перемычка окна в ипари для пресета. */
  lintelIpari?: number;
}
