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
 * поэтому сервис не знает ни о Pixi, ни о типах элементов. Высота пока не
 * учитывается: это плоский срез для предпросмотра.
 */
export class SceneVisibilityService {
  /**
   * Карта видимости из точки.
   *
   * @param radius Радиус обзора в единицах сцены; без него — всё поле.
   */
  map(grid: SceneSurfaceGrid, from: ScenePoint, radius = Number.POSITIVE_INFINITY): SceneVisibility {
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

        if (index === origin || this.clearLine(grid, from, center, origin, index)) {
          visible[index] = 1;
        }
      }
    }

    return { columns: grid.columns, rows: grid.rows, visible };
  }

  /** Видна ли точка из точки обзора. */
  sees(grid: SceneSurfaceGrid, from: ScenePoint, to: ScenePoint): boolean {
    const origin = surfaceIndex(grid, from[0], from[1]);
    const target = surfaceIndex(grid, to[0], to[1]);
    if (origin < 0 || target < 0) {
      return false;
    }

    return origin === target || this.clearLine(grid, from, to, origin, target);
  }

  /** Луч не пересекает непрозрачных клеток, кроме исходной и целевой. */
  private clearLine(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    to: ScenePoint,
    origin: number,
    target: number,
  ): boolean {
    const distance = Math.hypot(to[0] - from[0], to[1] - from[1]);
    const step = Math.max(1, grid.cell * SCENE_SURFACE.sightStep);
    const steps = Math.max(1, Math.ceil(distance / step));

    for (let index = 1; index < steps; index += 1) {
      const t = index / steps;
      const cell = surfaceIndex(
        grid,
        from[0] + (to[0] - from[0]) * t,
        from[1] + (to[1] - from[1]) * t,
      );
      if (cell < 0 || cell === origin || cell === target) {
        continue;
      }
      if (grid.opaque[cell]) {
        return false;
      }
    }

    return true;
  }
}
