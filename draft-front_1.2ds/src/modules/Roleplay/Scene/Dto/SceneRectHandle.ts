import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Ручка прямоугольника: восемь сторон и поворот. */
export type SceneRectHandle = 'nw' | 'n' | 'ne' | 'e' | 'se' | 's' | 'sw' | 'w';

/** Ручка прямоугольника в мировых координатах. */
export interface SceneRectHandlePoint {
  handle: SceneRectHandle;
  point: ScenePoint;
}
