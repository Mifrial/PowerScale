import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import type { SceneVisibility } from '@/modules/Roleplay/Scene/Dto/SceneVisibility';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { surfaceFloor, surfaceIndex } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { sceneBodyHeight, sceneBodyIpari, sceneSightEyeIpari } from '@/modules/Roleplay/Scene/Service/SceneSight';

/** Октанты тени: `xx`, `xy`, `yx`, `yy` для двух осей обхода. */
const OCTANTS: readonly [number, number, number, number][] = [
  [1, 0, 0, 1],
  [0, 1, 1, 0],
  [0, -1, 1, 0],
  [-1, 0, 0, 1],
  [-1, 0, 0, -1],
  [0, -1, -1, 0],
  [0, 1, -1, 0],
  [1, 0, 0, -1],
];

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
   * Теневое литьё по клеткам: непрозрачная клетка помечается видимой и
   * обрывает тень за собой, поэтому на сцену хватает O(площади обзора), а не
   * луча на каждую клетку. Для карты тумана достаточно высоты глаз: тело цели
   * учитывает `sees`, когда важно увидеть голову над низким забором.
   *
   * @param radius Радиус обзора в единицах сцены; без него — всё поле.
   * @param eye Высота глаз наблюдателя, ипари.
   * @param body Высота тела цели, ипари; карта тумана строит срез по глазам.
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
    void body;
    const visible = new Uint8Array(grid.columns * grid.rows);
    const origin = surfaceIndex(grid, from[0], from[1]);
    if (origin < 0) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    const fromFloor = surfaceFloor(grid, from, level) / grid.resolution;
    const start = fromFloor + this.eyeHeight(grid, origin, eye, level === 0);
    if (this.insideBand(grid, origin, start)) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    // Непрозрачность зависит только от высоты глаз, поэтому её можно снять
    // битовой маской и отдать общему теневому литью.
    const opaque = new Uint8Array(grid.columns * grid.rows);
    for (let index = 0; index < opaque.length; index += 1) {
      if (this.opaqueAt(grid, index, eye)) {
        opaque[index] = 1;
      }
    }

    return {
      columns: grid.columns,
      rows: grid.rows,
      visible: this.shadowMask(grid.x, grid.y, grid.columns, grid.rows, grid.cell, from, radius, opaque),
    };
  }

  /**
   * Теневое литьё по битовой маске непрозрачности.
   *
   * Одна и та же механика считает и своё поле (непрозрачность по высоте глаз),
   * и тень стен для чужих этажей (непрозрачность по растру стен). Поэтому
   * формула обзора у всех полей одинаковая, а стены не перебираются лучом.
   *
   * @param x Левый верхний угол сетки в единицах сцены.
   * @param y Верхний край сетки.
   * @param columns Ширина сетки.
   * @param rows Высота сетки.
   * @param cell Размер клетки.
   * @param from Точка обзора.
   * @param radius Радиус обзора; `Infinity` — до края поля.
   * @param opaque Маска непрозрачности, `1` — клетка перекрывает взгляд.
   *
   * @returns Маска видимости, `1` — клетка видна.
   */
  private shadowMask(
    x: number,
    y: number,
    columns: number,
    rows: number,
    cell: number,
    from: ScenePoint,
    radius: number,
    opaque: Uint8Array,
  ): Uint8Array {
    const visible = new Uint8Array(columns * rows);
    const origin = this.indexAt(x, y, columns, rows, cell, from[0], from[1]);
    if (origin < 0) {
      return visible;
    }

    visible[origin] = 1;
    const originColumn = origin % columns;
    const originRow = Math.floor(origin / columns);
    const diagonal = Math.ceil(Math.hypot(columns, rows));
    const maxDistance = Number.isFinite(radius) ? Math.min(Math.ceil(radius / cell), diagonal) : diagonal;

    // Восемь октантов: каждый обходит свою четверть и передаёт тени дальше.
    for (const [xx, xy, yx, yy] of OCTANTS) {
      this.castMask(
        columns,
        rows,
        cell,
        visible,
        opaque,
        originColumn,
        originRow,
        1,
        1,
        0,
        xx,
        xy,
        yx,
        yy,
        radius,
        maxDistance,
      );
    }

    return visible;
  }

  /** Индекс клетки по точке; `-1` — точка вне сетки. */
  indexAt(x: number, y: number, columns: number, rows: number, cell: number, pointX: number, pointY: number): number {
    const column = Math.floor((pointX - x) / cell);
    const row = Math.floor((pointY - y) / cell);
    if (column < 0 || row < 0 || column >= columns || row >= rows) {
      return -1;
    }

    return row * columns + column;
  }

  /**
   * Рекурсивный обход одного октанта по маске непрозрачности.
   *
   * @param row Текущая строка октанта.
   * @param start Начальный наклон луча.
   * @param end Конечный наклон луча.
   * @param xx Смещение колонки по x.
   * @param xy Смещение колонки по y.
   * @param yx Смещение строки по x.
   * @param yy Смещение строки по y.
   */
  private castMask(
    columns: number,
    rows: number,
    cell: number,
    visible: Uint8Array,
    opaque: Uint8Array,
    originColumn: number,
    originRow: number,
    row: number,
    start: number,
    end: number,
    xx: number,
    xy: number,
    yx: number,
    yy: number,
    radius: number,
    maxDistance: number,
  ): void {
    if (start < end) {
      return;
    }

    let newStart = start;
    for (let distance = row; distance <= maxDistance; distance += 1) {
      const dy = -distance;
      let blocked = false;
      for (let dx = -distance; dx <= 0; dx += 1) {
        const left = (dx - 0.5) / (dy + 0.5);
        const right = (dx + 0.5) / (dy - 0.5);
        if (start < right) {
          continue;
        }
        if (end > left) {
          break;
        }

        const column = originColumn + dx * xx + dy * xy;
        const line = originRow + dx * yx + dy * yy;
        if (column < 0 || column >= columns || line < 0 || line >= rows) {
          continue;
        }

        const index = line * columns + column;
        // Радиус — круг, а не квадрат: `distance` в октанте чебышёвское,
        // для границы обзора нужна евклидова длина до центра клетки.
        if (!Number.isFinite(radius) || Math.hypot(dx, dy) * cell <= radius) {
          visible[index] = 1;
        }

        if (opaque[index] === 1) {
          if (!blocked) {
            blocked = true;
            newStart = right;
          }
        } else if (blocked) {
          this.castMask(
            columns,
            rows,
            cell,
            visible,
            opaque,
            originColumn,
            originRow,
            distance + 1,
            newStart,
            left,
            xx,
            xy,
            yx,
            yy,
            radius,
            maxDistance,
          );
          blocked = false;
          newStart = right;
        }
      }

      if (blocked) {
        break;
      }
      start = newStart;
    }
  }

  /** Клетка перекрывает взгляд на высоте глаз. */
  private opaqueAt(grid: SceneSurfaceGrid, index: number, eye: number): boolean {
    const bottom = grid.sightBottom[index];
    if (!Number.isFinite(bottom)) {
      return false;
    }

    return eye > bottom && eye < grid.sightTop[index];
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
