import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Геометрия элемента поля. */
export type SceneShape =
  | { type: 'rect'; x: number; y: number; w: number; h: number }
  | { type: 'circle'; cx: number; cy: number; r: number }
  | { type: 'wall'; points: ScenePoint[]; width: number }
  | { type: 'polygon'; points: ScenePoint[] }
  | { type: 'point'; x: number; y: number };
