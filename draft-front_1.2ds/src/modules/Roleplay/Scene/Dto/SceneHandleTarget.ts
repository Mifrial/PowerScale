import type { SceneHandleSide } from '@/modules/Roleplay/Scene/Enum/SceneHandleSide';
import type { SceneVertexTarget } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';

/** Ручка Безье под курсором. */
export interface SceneHandleTarget extends SceneVertexTarget {
  side: SceneHandleSide;
}
