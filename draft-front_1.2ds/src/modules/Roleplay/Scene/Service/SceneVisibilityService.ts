import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import type { SceneVisibility } from '@/modules/Roleplay/Scene/Dto/SceneVisibility';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { surfaceCenter, surfaceFloor, surfaceIndex } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';

/**
 * Видимость клеток поля.
 *
 * Луч идёт по мировым координатам и проверяет непрозрачные клетки сетки. Сами
 * перекрытия (препятствия, стволы, валун, настил) уже размечены
 * `SceneSurfaceService`, поэтому сервис не знает ни о Pixi, ни о типах
 * элементов. Высота учитывается: луч идёт из глаз наблюдателя к телу цели, и
 * клетка перекрывает его только тогда, когда луч проходит через её полосу
 * `sightBottom…sightTop`. Поэтому ствол закрывает токен, крона, начинающаяся
 * выше глаз, — нет, а настил не пускает луч сквозь себя, но не мешает смотреть
 * с него и из-под него.
 */
export class SceneVisibilityService {
  /**
   * Карта видимости из точки.
   *
   * @param radius Радиус обзора в единицах сцены; без него — всё поле.
   * @param eye Высота глаз наблюдателя, ипари.
   * @param target Высота тела цели, ипари.
   * @param level Уровень наблюдателя: 0 — поверхность, выше — настил.
   */
  map(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    radius = Number.POSITIVE_INFINITY,
    eye = SCENE_SURFACE.eyeIpari,
    target = SCENE_SURFACE.targetIpari,
    level = 0,
  ): SceneVisibility {
    const visible = new Uint8Array(grid.columns * grid.rows);
    const origin = surfaceIndex(grid, from[0], from[1]);
    if (origin < 0) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    const fromFloor = surfaceFloor(grid, from, level) / grid.resolution;
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

        if (index === origin || this.clearLine(grid, from, center, origin, index, eye, target, fromFloor, fromFloor)) {
          visible[index] = 1;
        }
      }
    }

    return { columns: grid.columns, rows: grid.rows, visible };
  }

  /**
   * Видна ли точка из точки обзора.
   *
   * Уровни нужны настилу: с настила и из-под него луч стартует от своего пола,
   * поэтому мост не ослепляет ни того, кто на нём, ни того, кто под ним.
   */
  sees(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    to: ScenePoint,
    eye = SCENE_SURFACE.eyeIpari,
    target = SCENE_SURFACE.targetIpari,
    fromLevel = 0,
    toLevel = 0,
  ): boolean {
    const origin = surfaceIndex(grid, from[0], from[1]);
    const index = surfaceIndex(grid, to[0], to[1]);
    if (origin < 0 || index < 0) {
      return false;
    }

    if (origin === index) {
      return true;
    }

    const fromFloor = surfaceFloor(grid, from, fromLevel) / grid.resolution;
    const toFloor = surfaceFloor(grid, to, toLevel) / grid.resolution;

    return this.clearLine(grid, from, to, origin, index, eye, target, fromFloor, toFloor);
  }

  /**
   * Высота глаз с поправкой на потолок, ипари.
   *
   * Под настилом тело упирается в низ перекрытия: глаза не могут быть выше
   * зазора, иначе луч из-под моста начинался бы внутри самого настила.
   * `Infinity` и неположительный зазор — потолка нет.
   */
  private eyeHeight(grid: SceneSurfaceGrid, cell: number, height: number): number {
    const clearance = grid.clearance[cell] / grid.resolution;
    if (!Number.isFinite(clearance) || clearance <= 0) {
      return height;
    }

    return Math.min(height, clearance);
  }

  /**
   * Луч не пересекает непрозрачных клеток, кроме исходной и целевой.
   *
   * Высота луча отсчитывается от пола: `fromFloor` и `toFloor` — в ипари.
   */
  private clearLine(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    to: ScenePoint,
    origin: number,
    target: number,
    eye: number,
    targetHeight: number,
    fromFloor: number,
    toFloor: number,
  ): boolean {
    const distance = Math.hypot(to[0] - from[0], to[1] - from[1]);
    const step = Math.max(1, grid.cell * SCENE_SURFACE.sightStep);
    const steps = Math.max(1, Math.ceil(distance / step));
    const start = fromFloor + this.eyeHeight(grid, origin, eye);
    const finish = toFloor + this.eyeHeight(grid, target, targetHeight);

    for (let index = 1; index < steps; index += 1) {
      const t = index / steps;
      const cell = surfaceIndex(grid, from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t);
      if (cell < 0 || cell === origin || cell === target) {
        continue;
      }
      // Высота луча идёт от глаз наблюдателя к телу цели. Клетка перекрывает,
      // только если луч проходит через её полосу: выше потолка или ниже низа
      // видно свободно.
      const height = start + (finish - start) * t;
      if (grid.sightBottom[cell] <= height && height <= grid.sightTop[cell]) {
        return false;
      }
    }

    return true;
  }
}
