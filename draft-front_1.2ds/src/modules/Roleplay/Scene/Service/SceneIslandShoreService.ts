import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneShapeProbe } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';

/** Сколько точек контура проверяется на «вокруг вода». */
const SURROUND_SAMPLES = 24;

/** Остров в воде: суша и водоём, который задаёт его берег. */
export interface SceneIslandShore {
  /** Тело острова: `overWater`-террейн, не вложенный в другой такой же. */
  island: SceneElement;
  /** Вода, внутри которой лежит остров. */
  water: SceneElement;
  /**
   * Тесселированный контур острова.
   *
   * Он же — дырка в поле глубины воды: по ней вода считает мель, накат и мокрый
   * песок вокруг острова, как у своего собственного уреза.
   */
  contour: ScenePoint[];
}

/**
 * Острова, которые лежат в воде целиком.
 *
 * Остров — это `overWater`-террейн, а не любой пол поверх воды: пол дома и
 * дорожка на острове тоже помечены `overWater`, но берега у них нет. Поэтому
 * берётся только самый внешний контур, а внутренние элементы, вложенные в
 * него, пропускаются.
 *
 * Полуостров и насыпь, которые упираются в сушу, тоже пропускаются: их контур
 * выходит за воду, и берег от воды нарисовался бы вдоль сухого примыкания.
 * У них остаётся собственная кромка террейна.
 *
 * @param elements Элементы поля.
 * @param resolution Разрешение поля, единиц на ипари.
 */
export function sceneIslandShores(elements: readonly SceneElement[], resolution: number): SceneIslandShore[] {
  const base = Math.max(1, resolution);
  const step = Math.max(2, base * 0.1);
  const waters = elements
    .filter((element) => element.kind === 'terrain' && element.props.kind === 'water')
    .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
  const land = elements.filter((element) => element.kind === 'terrain' && element.props.overWater === true);
  if (waters.length === 0 || land.length === 0) {
    return [];
  }

  const waterProbes = new Map<number, SceneShapeProbe>();
  const probeOf = (element: SceneElement): SceneShapeProbe => {
    const cached = waterProbes.get(element.id);
    if (cached !== undefined) {
      return cached;
    }

    const probe = sceneGeometryService.probe(element.shape, sceneRotation(element));
    waterProbes.set(element.id, probe);

    return probe;
  };

  const shores: SceneIslandShore[] = [];
  for (const island of land) {
    const contour = contourOf(island.shape);
    if (contour.length < 3) {
      continue;
    }

    // Пол дома и дорожка лежат внутри острова: их берег — не их дело.
    if (land.some((other) => other.id !== island.id && containsElement(other, island))) {
      continue;
    }

    // Верхняя вода в порядке рисования: если водоёмы перекрываются, берег
    // принадлежит тому, который нарисован последним.
    for (let index = waters.length - 1; index >= 0; index -= 1) {
      const water = waters[index];
      if (!surroundedBy(probeOf(water), contour, step)) {
        continue;
      }

      shores.push({ island, water, contour });
      break;
    }
  }

  return shores;
}

/** Контур фигуры в единицах сцены: полигон тесселируется, остальное — по вершинам. */
function contourOf(shape: SceneShape): ScenePoint[] {
  if (shape.type === 'polygon') {
    return sceneCurveService.flattenContour(shape.points, shape.handles ?? []);
  }

  if (shape.type === 'rect') {
    return [
      [shape.x, shape.y],
      [shape.x + shape.w, shape.y],
      [shape.x + shape.w, shape.y + shape.h],
      [shape.x, shape.y + shape.h],
    ];
  }

  if (shape.type === 'circle') {
    const segments = 64;
    const points: ScenePoint[] = [];
    for (let index = 0; index < segments; index += 1) {
      const angle = (index / segments) * Math.PI * 2;
      points.push([shape.cx + Math.cos(angle) * shape.r, shape.cy + Math.sin(angle) * shape.r]);
    }

    return points;
  }

  return [];
}

/** Вложен ли элемент `inner` в `outer`: границы и точка внутри контура. */
function containsElement(outer: SceneElement, inner: SceneElement): boolean {
  const bounds = sceneGeometryService.bounds(inner.shape, sceneRotation(inner));
  const outerBounds = sceneGeometryService.bounds(outer.shape, sceneRotation(outer));
  if (
    bounds.x < outerBounds.x ||
    bounds.y < outerBounds.y ||
    bounds.x + bounds.width > outerBounds.x + outerBounds.width ||
    bounds.y + bounds.height > outerBounds.y + outerBounds.height
  ) {
    return false;
  }

  const center: ScenePoint = [bounds.x + bounds.width / 2, bounds.y + bounds.height / 2];

  return sceneGeometryService.contains(outer.shape, center, 0, sceneRotation(outer));
}

/**
 * Лежит ли контур целиком в воде с запасом.
 *
 * Наружная точка каждого сэмпла обязана быть водой: так остров отличается от
 * полуострова, у которого часть контура примыкает к суше.
 */
function surroundedBy(probe: SceneShapeProbe, contour: ScenePoint[], step: number): boolean {
  const sign = orientation(contour);
  const stride = Math.max(1, Math.ceil(contour.length / SURROUND_SAMPLES));
  for (let index = 0; index < contour.length; index += stride) {
    const point = contour[index];
    if (!probe.contains(point, 0)) {
      return false;
    }

    const previous = contour[(index + contour.length - 1) % contour.length];
    const next = contour[(index + 1) % contour.length];
    const dx = next[0] - previous[0];
    const dy = next[1] - previous[1];
    const length = Math.hypot(dx, dy);
    if (length < 1e-9) {
      continue;
    }

    const outside: ScenePoint = [point[0] + (dy / length) * sign * step, point[1] - (dx / length) * sign * step];
    if (!probe.contains(outside, 0)) {
      return false;
    }
  }

  return true;
}

/** Знак обхода контура: наружная нормаль ребра смотрит в сторону `sign`. */
function orientation(contour: ScenePoint[]): number {
  let doubled = 0;
  for (let index = 0; index < contour.length; index += 1) {
    const current = contour[index];
    const next = contour[(index + 1) % contour.length];
    doubled += current[0] * next[1] - next[0] * current[1];
  }

  return doubled >= 0 ? 1 : -1;
}
