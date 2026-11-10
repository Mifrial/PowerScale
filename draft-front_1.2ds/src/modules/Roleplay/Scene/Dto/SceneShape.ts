import type { SceneHandle } from '@/modules/Roleplay/Scene/Dto/SceneHandle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Геометрия элемента поля. */
export type SceneShape =
  | { type: 'rect'; x: number; y: number; w: number; h: number }
  | { type: 'circle'; cx: number; cy: number; r: number }
  | { type: 'wall'; points: ScenePoint[]; width: number }
  | { type: 'polygon'; points: ScenePoint[]; handles?: SceneHandle[] }
  | { type: 'path'; points: ScenePoint[]; widths: number[]; handles?: SceneHandle[] }
  | { type: 'point'; x: number; y: number };
