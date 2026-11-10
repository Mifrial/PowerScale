import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Ручки Безье узла: `in` и `out` — смещения относительно самой точки. */
export interface SceneHandle {
  in: ScenePoint;
  out: ScenePoint;
}
