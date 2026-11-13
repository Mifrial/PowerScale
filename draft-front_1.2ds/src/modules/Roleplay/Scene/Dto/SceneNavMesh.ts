import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRampSlope } from '@/modules/Roleplay/Scene/Dto/SceneRampSlope';

/** Тип перехода между уровнями. */
export type SceneNavTransitionType = 'walk' | 'climb' | 'drop';

/**
 * Переход между уровнями.
 *
 * `walk` — обычный шаг: перепад в пределах `stepIpari` или постепенный съезд.
 * `climb` — подъём/подтягивание в пределах `climbIpari`. `drop` — безопасный
 * спуск в пределах `dropIpari`. Перепад больше порога звена не создаёт.
 */
export interface SceneNavTransition {
  type: SceneNavTransitionType;
  /** Перепад высот: положительный — вверх. */
  delta: number;
}

/** Сосед треугольника по общему ребру. */
export interface SceneNavLink {
  /** Индекс соседнего треугольника. */
  to: number;
  /** Концы общего ребра; порядок не важен. */
  left: ScenePoint;
  right: ScenePoint;
  /** Длина общего ребра. */
  length: number;
  /** Переход между уровнями; у обычного звена — `null`. */
  transition: SceneNavTransition | null;
}

/** Треугольник навигационной сетки. */
export interface SceneNavTriangle {
  points: [ScenePoint, ScenePoint, ScenePoint];
  /** Скорость поверхности: 100 — норма. */
  speed: number;
  /** Уровень поверхности: 0 — базовый слой, больше — настилы. */
  level: number;
  /** Высота уровня над нулём поля, единицы сцены. */
  elevation: number;
  centroid: ScenePoint;
  links: SceneNavLink[];
}

/** Область одинаковой скорости, из которой собрана сетка. */
export interface SceneNavRegion {
  speed: number;
  /** Уровень поверхности: 0 — базовый слой, больше — настилы. */
  level: number;
  /** Высота уровня над нулём поля, единицы сцены. */
  elevation: number;
  /** Съезд: высота меняется вдоль длинной стороны; у настила — `null`. */
  slope: SceneRampSlope | null;
  outer: ScenePoint[];
  holes: ScenePoint[][];
}

/** Навигационная сетка поля. */
export interface SceneNavMesh {
  triangles: SceneNavTriangle[];
  regions: SceneNavRegion[];
  /** Радиус, под который построена сетка. */
  agentRadius: number;
  /** Разрешение локации: единицы сцены в ипари. */
  resolution: number;
  /** Максимальная скорость сцены: нужна для эвристики A*. */
  maxSpeed: number;
  bounds: SceneBounds;
}

/** Участок маршрута на одном уровне. */
export interface SceneNavSegment {
  level: number;
  /** Высота уровня над нулём поля, единицы сцены. */
  elevation: number;
  /** Участок проходит под более высокой поверхностью: рисуется приглушённо. */
  covered: boolean;
  /** Переход с предыдущего участка; у первого участка — `null`. */
  transition: SceneNavTransition | null;
  points: ScenePoint[];
}

/** Маршрут по навигационной сетке. */
export interface SceneNavRoute {
  points: ScenePoint[];
  /** Участки по уровням: по настилу, под настилом, по земле. */
  segments: SceneNavSegment[];
  /** Длина полилинии в единицах сцены. */
  distance: number;
  /** Стоимость прохода: длина с поправкой на скорость и переходы. */
  cost: number;
}
