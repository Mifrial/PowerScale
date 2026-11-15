import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import type { SceneVisibility } from '@/modules/Roleplay/Scene/Dto/SceneVisibility';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { surfaceCenter, surfaceIndex } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';

/**
 * Видимость клеток поля.
 *
 * Луч идёт по мировым координатам и проверяет непрозрачные клетки сетки. Сами
 * перекрытия (препятствия, стволы, валун) уже размечены `SceneSurfaceService`,
 * поэтому сервис не знает ни о Pixi, ни о типах элементов. Высота учитывается:
 * луч идёт из глаз наблюдателя к телу цели, и клетка перекрывает его только
 * тогда, когда её силуэт начинается не выше луча. Поэтому ствол закрывает
 * токен, а крона, начинающаяся выше глаз, — нет.
 */
export class SceneVisibilityService {
  /**
   * Карта видимости из точки.
   *
   * @param radius Радиус обзора в единицах сцены; без него — всё поле.
   * @param eye Высота глаз наблюдателя, ипари.
   * @param target Высота тела цели, ипари.
   */
  map(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    radius = Number.POSITIVE_INFINITY,
    eye = SCENE_SURFACE.eyeIpari,
    target = SCENE_SURFACE.targetIpari,
  ): SceneVisibility {
    const visible = new Uint8Array(grid.columns * grid.rows);
    const origin = surfaceIndex(grid, from[0], from[1]);
    if (origin < 0) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    const originColumn = origin % grid.columns;
    const originRow = Math.floor(origin / grid.columns);
    const reach = Math.ceil(radius / grid.cell);
    const minRow = Math.max(0, originRow - reach);
    const maxRow = Math.min(grid.rows - 1, originRow + reach);
    const minColumn = Math.max(0, originColumn - reach);
    const maxColumn = Math.min(grid.columns - 1, originColumn + reach);

    for (let row = minRow; row <= maxRow; row += 1) {
      for (let column = minColumn; column <= maxColumn; column += 1) {
        const index = row * grid.columns + column;
        const center = surfaceCenter(grid, index);
        if (index !== origin && Math.hypot(center[0] - from[0], center[1] - from[1]) > radius) {
          continue;
        }

        if (index === origin || this.clearLine(grid, from, center, origin, index, eye, target)) {
          visible[index] = 1;
        }
      }
    }

    this.revealOccluders(grid, visible);

    return { columns: grid.columns, rows: grid.rows, visible };
  }

  /**
   * Объект, перекрывающий обзор, виден сам.
   *
   * Луч упирается в ближнюю клетку силуэта, поэтому дальние клетки того же
   * ствола или валуна оставались скрытыми: туман ложился на сам предмет, и
   * камень темнел целиком, хотя именно он и загораживает вид. Видимая клетка
   * зажигает связанную с ней область перекрытия: предмет виден целиком, а
   * туман начинается за ним.
   *
   * Связность по четырём соседям, а не по восьми: диагональ не должна
   * сращивать два отдельных предмета через угол.
   */
  private revealOccluders(grid: SceneSurfaceGrid, visible: Uint8Array): void {
    const queue: number[] = [];
    for (let index = 0; index < visible.length; index += 1) {
      if (visible[index] === 1 && Number.isFinite(grid.sightBottom[index])) {
        queue.push(index);
      }
    }

    for (let head = 0; head < queue.length; head += 1) {
      const index = queue[head];
      const column = index % grid.columns;
      const row = Math.floor(index / grid.columns);
      const neighbors = [
        column > 0 ? index - 1 : -1,
        column < grid.columns - 1 ? index + 1 : -1,
        row > 0 ? index - grid.columns : -1,
        row < grid.rows - 1 ? index + grid.columns : -1,
      ];
      for (const neighbor of neighbors) {
        if (neighbor < 0 || visible[neighbor] === 1) {
          continue;
        }
        if (!Number.isFinite(grid.sightBottom[neighbor])) {
          continue;
        }

        visible[neighbor] = 1;
        queue.push(neighbor);
      }
    }
  }

  /** Видна ли точка из точки обзора. */
  sees(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    to: ScenePoint,
    eye = SCENE_SURFACE.eyeIpari,
    target = SCENE_SURFACE.targetIpari,
  ): boolean {
    const origin = surfaceIndex(grid, from[0], from[1]);
    const index = surfaceIndex(grid, to[0], to[1]);
    if (origin < 0 || index < 0) {
      return false;
    }

    return origin === index || this.clearLine(grid, from, to, origin, index, eye, target);
  }

  /** Луч не пересекает непрозрачных клеток, кроме исходной и целевой. */
  private clearLine(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    to: ScenePoint,
    origin: number,
    target: number,
    eye: number,
    targetHeight: number,
  ): boolean {
    const distance = Math.hypot(to[0] - from[0], to[1] - from[1]);
    const step = Math.max(1, grid.cell * SCENE_SURFACE.sightStep);
    const steps = Math.max(1, Math.ceil(distance / step));

    for (let index = 1; index < steps; index += 1) {
      const t = index / steps;
      const cell = surfaceIndex(grid, from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t);
      if (cell < 0 || cell === origin || cell === target) {
        continue;
      }
      // Высота луча падает от глаз наблюдателя к телу цели; силуэт перекрывает,
      // только если начинается не выше луча.
      const height = eye + (targetHeight - eye) * t;
      if (grid.sightBottom[cell] <= height) {
        return false;
      }
    }

    return true;
  }
}
