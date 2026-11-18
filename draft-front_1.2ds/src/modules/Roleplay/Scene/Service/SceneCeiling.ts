import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';

/** Минимум элемента для расчёта потолка: черновик и сцена подходят оба. */
type SceneCeilingElement = Pick<SceneElement, 'id' | 'kind' | 'shape' | 'props' | 'targetBoardId'>;
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import { SCENE_FLOOR } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_FLOOR';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneSurfaceElevation } from '@/modules/Roleplay/Scene/Service/SceneHeight';

/** Полы, у которых есть потолок: доски и плитка. */
const FLOOR_KINDS: readonly string[] = ['wood', 'tile'];

/**
 * Верх потолка пола или `null`, если пол не задаёт потолок.
 *
 * Свойство `ceiling` — длина в единицах сцены; `0` означает открытое небо.
 * У старых полов поля нет: для досок и плитки берётся высота этажа по
 * умолчанию, поэтому дом без явного потолка остаётся закрытым.
 */
export function sceneFloorCeilingTop(element: SceneCeilingElement, resolution: number): number | null {
  if (element.kind !== 'terrain' || !FLOOR_KINDS.includes(element.props.kind ?? '')) {
    return null;
  }

  const ceiling = element.props.ceiling;
  const height = ceiling === undefined ? SCENE_FLOOR.storeyIpari * Math.max(1, resolution) : ceiling;
  if (height <= 0) {
    return null;
  }

  return sceneSurfaceElevation(element) + height;
}

/**
 * Потолок пола под стеной; `null` — стена стоит не на полу.
 *
 * Стена лежит на кромке пола, поэтому её ось может быть на волосок снаружи:
 * проверка идёт с допуском в половину толщины стены. Если стена разделяет две
 * комнаты с разными потолками, берётся более высокий — стена обязана закрыть
 * обе.
 */
export function sceneWallCeiling(
  element: SceneCeilingElement,
  elements: readonly SceneCeilingElement[],
  resolution: number,
): number | null {
  if (element.kind !== 'obstacle' || element.shape.type !== 'wall') {
    return null;
  }

  const tolerance = Math.max(1, element.shape.width / 2) + 1;
  const floors = elements.filter((floor) => sceneFloorCeilingTop(floor, resolution) !== null);
  let top: number | null = null;
  for (const point of wallProbePoints(element.shape.points)) {
    for (const floor of floors) {
      if (!sceneGeometryService.contains(floor.shape, point, tolerance)) {
        continue;
      }

      const ceiling = sceneFloorCeilingTop(floor, resolution);
      if (ceiling !== null && (top === null || ceiling > top)) {
        top = ceiling;
      }
    }
  }

  return top;
}

/** Верх стены: потолок пола, а без пола — собственная высота. */
export function sceneWallTop(element: SceneElement, elements: readonly SceneElement[], resolution: number): number {
  const ceiling = sceneWallCeiling(element, elements, resolution);
  if (ceiling !== null) {
    return ceiling;
  }

  return sceneObstacleTop(element);
}

/** Верх препятствия: отметка плюс высота; без высоты — до неба. */
export function sceneObstacleTop(element: SceneCeilingElement): number {
  const height = element.props.height;
  if (typeof height !== 'number' || !Number.isFinite(height)) {
    return Number.POSITIVE_INFINITY;
  }

  return sceneSurfaceElevation(element) + Math.max(0, height);
}

/** Точки вдоль осевой стены: концы, середины и четверти сегментов. */
function wallProbePoints(points: readonly ScenePoint[]): ScenePoint[] {
  const result: ScenePoint[] = [];
  for (let index = 1; index < points.length; index += 1) {
    const [ax, ay] = points[index - 1];
    const [bx, by] = points[index];
    for (const t of [0, 0.25, 0.5, 0.75, 1]) {
      result.push([ax + (bx - ax) * t, ay + (by - ay) * t]);
    }
  }

  return result;
}
