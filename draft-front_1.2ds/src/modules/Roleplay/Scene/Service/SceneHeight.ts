import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRampSlope } from '@/modules/Roleplay/Scene/Dto/SceneRampSlope';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

/**
 * Всё, что нужно для расчёта высот: элемент сцены или элемент черновика.
 *
 * Помощники не зависят от `asset` и `sortOrder`, поэтому принимают только
 * геометрию, вид и свойства.
 */
export interface SceneHeightElement {
  id: number;
  kind: SceneElementKind;
  shape: SceneShape;
  props: SceneElementProps;
}

/** Доли длинной стороны настила, по которым ищется поверхность под ним. */
const DECK_SAMPLES: readonly number[] = [0.5, 0.25, 0.75];

/** Настил: мост или съезд. */
export function isSceneDeck(element: SceneHeightElement): boolean {
  return element.kind === 'support' && (element.props.kind === 'bridge' || element.props.kind === 'ramp');
}

/**
 * Высота верхней поверхности элемента над нулём поля, единицы сцены.
 *
 * Вода стоит на своей отметке (`SCENE_SURFACE.waterSurfaceIpari`), остальные
 * поверхности — на `props.elevation`, по умолчанию на нуле поля.
 */
export function sceneSurfaceElevation(element: SceneHeightElement, resolution: number): number {
  const elevation = element.props.elevation;
  if (typeof elevation === 'number' && Number.isFinite(elevation)) {
    return elevation;
  }

  return element.props.kind === 'water' ? Math.max(1, resolution) * SCENE_SURFACE.waterSurfaceIpari : 0;
}

/**
 * Толщина настила, единицы сцены.
 *
 * `props.height` у моста — вертикальный размер настила, а не высота объёма.
 * Без явного значения берётся пресет.
 */
export function sceneDeckThickness(element: SceneHeightElement, resolution: number): number {
  const height = element.props.height;
  if (typeof height === 'number' && Number.isFinite(height)) {
    return Math.max(0, height);
  }

  return Math.max(1, resolution) * SCENE_BRIDGE.thicknessIpari;
}

/** Самая высокая поверхность под точкой, единицы сцены. */
export function sceneSurfaceUnder(
  point: ScenePoint,
  elements: readonly SceneHeightElement[],
  resolution: number,
  excludeId = -1,
): number {
  let base = 0;
  for (const element of elements) {
    if (element.id === excludeId) {
      continue;
    }
    if (element.kind !== 'terrain' && element.kind !== 'support') {
      continue;
    }
    if (!sceneGeometryService.contains(element.shape, point, 0, sceneRotation(element))) {
      continue;
    }

    base = Math.max(base, sceneSurfaceElevation(element, resolution));
  }

  return base;
}

/**
 * Самая высокая поверхность под настилом, единицы сцены.
 *
 * Настил может лежать над водой, берегом, дорогой или другим настилом.
 * Точки берутся по центру и вдоль длинной стороны: они остаются внутри
 * прямоугольника при любом повороте, в отличие от углов описанного прямоугольника.
 */
export function sceneDeckBaseElevation(
  deck: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
): number {
  const rotation = sceneRotation(deck);
  const shape = deck.shape;
  const center = sceneGeometryService.center(shape, rotation);
  const half = shape.type === 'rect' ? (shape.w >= shape.h ? shape.w : shape.h) / 2 : 0;
  const horizontal = shape.type === 'rect' && shape.w >= shape.h;

  let base = 0;
  for (const share of DECK_SAMPLES) {
    const offset = (share - 0.5) * half * 2;
    const point: ScenePoint = horizontal ? [center[0] + offset, center[1]] : [center[0], center[1] + offset];
    base = Math.max(base, sceneSurfaceUnder(point, elements, resolution, deck.id));
  }

  return base;
}

/**
 * Наклон съезда.
 *
 * Съезд поднимается к дальнему концу по длинной стороне: низкий конец — там,
 * где начинается местная ось прямоугольника, высокий — напротив. Низкий конец
 * садится на поверхность под ним, высокий берётся из `props.elevation`.
 * Развернуть съезд можно поворотом на 180°.
 */
export function sceneRampSlope(
  element: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
): SceneRampSlope | null {
  if (element.props.kind !== 'ramp' || element.shape.type !== 'rect') {
    return null;
  }

  const shape = element.shape;
  const rotation = sceneRotation(element);
  const center = sceneGeometryService.center(shape, rotation);
  const horizontal = shape.w >= shape.h;
  const half = (horizontal ? shape.w : shape.h) / 2;
  const radians = (rotation * Math.PI) / 180;
  const axis: ScenePoint = horizontal
    ? [Math.cos(radians), Math.sin(radians)]
    : [-Math.sin(radians), Math.cos(radians)];
  const from: ScenePoint = [center[0] - axis[0] * half, center[1] - axis[1] * half];
  const to: ScenePoint = [center[0] + axis[0] * half, center[1] + axis[1] * half];
  const low = sceneSurfaceUnder(from, elements, resolution, element.id);
  const high = sceneSurfaceElevation(element, resolution);

  return high > low ? { from, to, low, high } : null;
}

/** Высота наклонной поверхности в точке: проекция на ось съезда. */
export function sceneSlopeElevation(slope: SceneRampSlope, point: ScenePoint): number {
  const dx = slope.to[0] - slope.from[0];
  const dy = slope.to[1] - slope.from[1];
  const length = Math.hypot(dx, dy);
  if (length <= 0) {
    return slope.high;
  }

  const t = Math.min(
    1,
    Math.max(0, ((point[0] - slope.from[0]) * dx + (point[1] - slope.from[1]) * dy) / (length * length)),
  );

  return slope.low + (slope.high - slope.low) * t;
}

/**
 * Зазор под настилом, единицы сцены.
 *
 * Считается от низа настила до самой высокой поверхности под ним. Значение
 * меньше роста тела означает, что пройти под настилом нельзя; отрицательное —
 * что настил лежит на поверхности.
 */
export function sceneDeckClearance(
  deck: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
): number {
  return (
    sceneSurfaceElevation(deck, resolution) -
    sceneDeckThickness(deck, resolution) -
    sceneDeckBaseElevation(deck, elements, resolution)
  );
}
