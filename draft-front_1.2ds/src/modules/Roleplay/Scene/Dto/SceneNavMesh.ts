import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Сосед треугольника по общему ребру. */
export interface SceneNavLink {
  /** Индекс соседнего треугольника. */
  to: number;
  /** Концы общего ребра; порядок не важен. */
  left: ScenePoint;
  right: ScenePoint;
  /** Длина общего ребра. */
  length: number;
}

/** Треугольник навигационной сетки. */
export interface SceneNavTriangle {
  points: [ScenePoint, ScenePoint, ScenePoint];
  /** Скорость поверхности: 100 — норма. */
  speed: number;
  centroid: ScenePoint;
  links: SceneNavLink[];
}

/** Область одинаковой скорости, из которой собрана сетка. */
export interface SceneNavRegion {
  speed: number;
  outer: ScenePoint[];
  holes: ScenePoint[][];
}

/** Навигационная сетка поля. */
export interface SceneNavMesh {
  triangles: SceneNavTriangle[];
  regions: SceneNavRegion[];
  /** Радиус, под который построена сетка. */
  agentRadius: number;
  /** Максимальная скорость сцены: нужна для эвристики A*. */
  maxSpeed: number;
  bounds: SceneBounds;
}

/** Маршрут по навигационной сетке. */
export interface SceneNavRoute {
  points: ScenePoint[];
  /** Длина полилинии в единицах сцены. */
  distance: number;
  /** Стоимость прохода: длина с поправкой на скорость. */
  cost: number;
}
