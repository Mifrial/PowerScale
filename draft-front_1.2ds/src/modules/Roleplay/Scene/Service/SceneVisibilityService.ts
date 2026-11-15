import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import type { SceneVisibility } from '@/modules/Roleplay/Scene/Dto/SceneVisibility';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { surfaceCenter, surfaceFloor, surfaceIndex } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import {
  sceneBodyHeight,
  sceneBodyIpari,
  sceneSightEyeIpari,
} from '@/modules/Roleplay/Scene/Service/SceneSight';

/**
 * Видимость клеток поля.
 *
 * Луч идёт по мировым координатам и проверяет непрозрачные клетки сетки. Сами
 * перекрытия (препятствия, стволы, валун, настил) уже размечены
 * `SceneSurfaceService`, поэтому сервис не знает ни о Pixi, ни о типах
 * элементов.
 *
 * Цель видна, если видна хотя бы одна точка её тела: луч проверяется не в одну
 * высоту, а по всему росту. Клетка с полосой `sightBottom…sightTop` закрывает
 * диапазон высот цели, поэтому по лучу собираются интервалы закрытых высот и
 * проверяется, покрыли ли они тело целиком. Так низкий забор не скрывает
 * голову, а ствол или стена скрывают всё.
 *
 * Глаза — 90% роста наблюдателя. Рост у персонажей разный, фиксированных
 * дробных высот в данных нет: рост по умолчанию —
 * `SCENE_NAVIGATION.bodyHeightIpari`, сцена хранит его целыми единицами.
 */
export class SceneVisibilityService {
  /** Закрытые высоты тела для текущего луча; переиспользуется без аллокаций. */
  private readonly blocked: number[] = [];

  /**
   * Карта видимости из точки.
   *
   * @param radius Радиус обзора в единицах сцены; без него — всё поле.
   * @param eye Высота глаз наблюдателя, ипари.
   * @param body Высота тела цели, ипари.
   * @param level Уровень наблюдателя: 0 — поверхность, выше — настил.
   */
  map(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    radius = Number.POSITIVE_INFINITY,
    eye = sceneSightEyeIpari(sceneBodyHeight(grid.resolution), grid.resolution),
    body = sceneBodyIpari(sceneBodyHeight(grid.resolution), grid.resolution),
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

        if (
          index === origin ||
          this.clearAny(grid, from, center, origin, index, eye, body, fromFloor, fromFloor, level, 0)
        ) {
          visible[index] = 1;
        }
      }
    }

    return { columns: grid.columns, rows: grid.rows, visible };
  }

  /**
   * Видна ли любая точка тела цели из точки обзора.
   *
   * Уровни нужны настилу: с настила и из-под него луч стартует от своего пола,
   * поэтому мост не ослепляет ни того, кто на нём, ни того, кто под ним.
   */
  sees(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    to: ScenePoint,
    eye = sceneSightEyeIpari(sceneBodyHeight(grid.resolution), grid.resolution),
    body = sceneBodyIpari(sceneBodyHeight(grid.resolution), grid.resolution),
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

    return this.clearAny(grid, from, to, origin, index, eye, body, fromFloor, toFloor, fromLevel, toLevel);
  }

  /**
   * Высота глаз с поправкой на потолок, ипари.
   *
   * Под настилом тело упирается в низ перекрытия: глаза не могут быть выше
   * зазора, иначе луч из-под моста начинался бы внутри самого настила.
   * На настиле зазор относится к пространству под ним и высоту не режет.
   * `Infinity` и неположительный зазор — потолка нет.
   */
  private eyeHeight(grid: SceneSurfaceGrid, cell: number, height: number, underCeiling: boolean): number {
    if (!underCeiling) {
      return height;
    }

    const clearance = grid.clearance[cell] / grid.resolution;
    if (!Number.isFinite(clearance) || clearance <= 0) {
      return height;
    }

    return Math.min(height, clearance);
  }

  /** Глаз внутри полосы исходной клетки: луч не выйдет наружу. */
  private insideBand(grid: SceneSurfaceGrid, cell: number, height: number): boolean {
    const bottom = grid.sightBottom[cell];
    if (!Number.isFinite(bottom)) {
      return false;
    }

    return height > bottom && height < grid.sightTop[cell];
  }

  /**
   * Луч проходит через полосу потолка в клетке.
   *
   * Берутся только полосы с конечным верхом: у стены и ствола верх — небо, и
   * их ловит обычная выборка по клеткам. Полоса настила тонкая, поэтому её
   * пересечение считается по концам луча, а не по центрам клеток.
   */
  private crossesCeiling(grid: SceneSurfaceGrid, cell: number, start: number, finish: number): boolean {
    const top = grid.sightTop[cell];
    if (!Number.isFinite(top)) {
      return false;
    }

    return Math.max(start, finish) > grid.sightBottom[cell] && Math.min(start, finish) < top;
  }

  /** Добавляет закрытый интервал высот тела с обрезкой по телу. */
  private pushInterval(lo: number, hi: number, min: number, max: number): void {
    const from = Math.max(min, lo);
    const to = Math.min(max, hi);
    if (to > from) {
      this.blocked.push(from, to);
    }
  }

  /** Покрыт ли весь рост цели закрытыми интервалами. */
  private covered(min: number, max: number): boolean {
    const values = this.blocked;
    for (let index = 2; index < values.length; index += 2) {
      const lo = values[index];
      const hi = values[index + 1];
      let cursor = index - 2;
      while (cursor >= 0 && values[cursor] > lo) {
        values[cursor + 2] = values[cursor];
        values[cursor + 3] = values[cursor + 1];
        cursor -= 2;
      }
      values[cursor + 2] = lo;
      values[cursor + 3] = hi;
    }

    let reach = min;
    for (let index = 0; index < values.length; index += 2) {
      if (values[index] > reach) {
        return false;
      }
      if (values[index + 1] > reach) {
        reach = values[index + 1];
      }
      if (reach >= max) {
        return true;
      }
    }

    return false;
  }

  /**
   * Видна ли хоть одна высота тела цели.
   *
   * Высота луча отсчитывается от пола: `fromFloor` и `toFloor` — в ипари.
   * Для клетки на доле пути `t` полоса `bottom…top` закрывает такие высоты
   * цели `finish`, при которых луч в этой клетке попадает в полосу:
   * `finish ∈ (start + (bottom − start)/t, start + (top − start)/t)`.
   * Интервалы сливаются, и цель видна, если они не покрыли весь рост.
   */
  private clearAny(
    grid: SceneSurfaceGrid,
    from: ScenePoint,
    to: ScenePoint,
    origin: number,
    target: number,
    eye: number,
    bodyHeight: number,
    fromFloor: number,
    toFloor: number,
    fromLevel: number,
    toLevel: number,
  ): boolean {
    const distance = Math.hypot(to[0] - from[0], to[1] - from[1]);
    const step = Math.max(1, grid.cell * SCENE_SURFACE.sightStep);
    const steps = Math.max(1, Math.ceil(distance / step));
    const start = fromFloor + this.eyeHeight(grid, origin, eye, fromLevel === 0);
    const body = this.eyeHeight(grid, target, bodyHeight, toLevel === 0);
    if (body <= 0) {
      return false;
    }

    if (this.insideBand(grid, origin, start)) {
      return false;
    }

    const min = toFloor;
    const max = toFloor + body;
    this.blocked.length = 0;

    // Тонкий настил у цели выборка по центрам клеток может проскочить.
    // Проверяем его, только когда наблюдатель сам стоит в клетке настила:
    // иначе луч подходит сбоку и может пройти над кромкой — с берега на мост.
    if (Number.isFinite(grid.clearance[origin]) && this.crossesCeiling(grid, target, start, max)) {
      return false;
    }

    for (let index = 1; index < steps; index += 1) {
      const t = index / steps;
      const cell = surfaceIndex(grid, from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t);
      if (cell < 0 || cell === origin || cell === target) {
        continue;
      }
      const bottom = grid.sightBottom[cell];
      if (!Number.isFinite(bottom)) {
        continue;
      }
      const top = grid.sightTop[cell];
      this.pushInterval(start + (bottom - start) / t, start + (top - start) / t, min, max);
    }

    return !this.covered(min, max);
  }
}
