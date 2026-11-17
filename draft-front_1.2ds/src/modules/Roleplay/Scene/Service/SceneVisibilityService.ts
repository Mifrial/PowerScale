import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import type { SceneVisibility } from '@/modules/Roleplay/Scene/Dto/SceneVisibility';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { surfaceFloor, surfaceIndex } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { sceneBodyHeight, sceneBodyIpari, sceneSightEyeIpari } from '@/modules/Roleplay/Scene/Service/SceneSight';

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
   * Каждая клетка в радиусе проверяется своим лучом: обход клеток по отрезку
   * (DDA) останавливается на первой непрозрачной клетке на высоте глаз.
   * Поэтому луч не «протекает» вбок от кромки проёма, как теневое литьё по
   * октантам, и своё поле считается той же геометрией, что и чужие этажи.
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
    const start = fromFloor + this.eyeHeight(grid, origin, eye, fromFloor);
    if (this.insideBand(grid, origin, start)) {
      return { columns: grid.columns, rows: grid.rows, visible };
    }

    const finite = Number.isFinite(radius);
    const reach = finite ? Math.max(0, radius) : Number.POSITIVE_INFINITY;
    const minColumn = finite ? this.clamp(Math.floor((from[0] - reach - grid.x) / grid.cell), 0, grid.columns - 1) : 0;
    const maxColumn = finite
      ? this.clamp(Math.floor((from[0] + reach - grid.x) / grid.cell), 0, grid.columns - 1)
      : grid.columns - 1;
    const minRow = finite ? this.clamp(Math.floor((from[1] - reach - grid.y) / grid.cell), 0, grid.rows - 1) : 0;
    const maxRow = finite
      ? this.clamp(Math.floor((from[1] + reach - grid.y) / grid.cell), 0, grid.rows - 1)
      : grid.rows - 1;
    const radiusSquared = finite ? reach * reach : Number.POSITIVE_INFINITY;

    for (let row = minRow; row <= maxRow; row += 1) {
      const centerY = grid.y + (row + 0.5) * grid.cell;
      const dy = centerY - from[1];
      for (let column = minColumn; column <= maxColumn; column += 1) {
        const centerX = grid.x + (column + 0.5) * grid.cell;
        const dx = centerX - from[0];
        if (dx * dx + dy * dy > radiusSquared) {
          continue;
        }

        const index = row * grid.columns + column;
        if (index === origin || this.rayClear(grid, from, centerX, centerY, start)) {
          visible[index] = 1;
        }
      }
    }

    return { columns: grid.columns, rows: grid.rows, visible };
  }

  /**
   * Отрезок из точки обзора свободен на высоте глаз.
   *
   * Обход Аманатидеса — Ву: проверяются только клетки, которые реально
   * пересекает луч. Клетка цели не проверяется — стену видно с её стороны,
   * даже если сама она непрозрачна.
   */
  private rayClear(grid: SceneSurfaceGrid, from: ScenePoint, toX: number, toY: number, eye: number): boolean {
    const cell = grid.cell;
    let column = Math.floor((from[0] - grid.x) / cell);
    let row = Math.floor((from[1] - grid.y) / cell);
    const endColumn = Math.floor((toX - grid.x) / cell);
    const endRow = Math.floor((toY - grid.y) / cell);
    if (column === endColumn && row === endRow) {
      return true;
    }

    const dx = toX - from[0];
    const dy = toY - from[1];
    const stepX = dx < 0 ? -1 : 1;
    const stepY = dy < 0 ? -1 : 1;
    const deltaX = dx === 0 ? Number.POSITIVE_INFINITY : Math.abs(cell / dx);
    const deltaY = dy === 0 ? Number.POSITIVE_INFINITY : Math.abs(cell / dy);
    const borderX = grid.x + (column + (stepX > 0 ? 1 : 0)) * cell;
    const borderY = grid.y + (row + (stepY > 0 ? 1 : 0)) * cell;
    let nextX = dx === 0 ? Number.POSITIVE_INFINITY : (borderX - from[0]) / dx;
    let nextY = dy === 0 ? Number.POSITIVE_INFINITY : (borderY - from[1]) / dy;
    let guard = grid.columns + grid.rows;

    while (guard > 0 && (column !== endColumn || row !== endRow)) {
      if (nextX < nextY) {
        column += stepX;
        nextX += deltaX;
      } else if (nextY < nextX) {
        row += stepY;
        nextY += deltaY;
      } else {
        // Луч прошёл ровно через общий угол клеток: проверяем обе боковые
        // клетки, иначе диагональ проскальзывает между двумя стенами.
        if (this.blockedCell(grid, column + stepX, row, eye) || this.blockedCell(grid, column, row + stepY, eye)) {
          return false;
        }
        column += stepX;
        row += stepY;
        nextX += deltaX;
        nextY += deltaY;
      }
      guard -= 1;
      if (column < 0 || row < 0 || column >= grid.columns || row >= grid.rows) {
        return true;
      }
      if (column === endColumn && row === endRow) {
        break;
      }
      // Клетка на пути: без вызовов и проверок границ — отрезок между двумя
      // клетками сетки всегда остаётся внутри её прямоугольника.
      const index = row * grid.columns + column;
      if (this.opaqueAt(grid, index, eye)) {
        return false;
      }
    }

    return true;
  }

  /** Клетка перекрывает луч на высоте глаз; вне сетки — нет. */
  private blockedCell(grid: SceneSurfaceGrid, column: number, row: number, eye: number): boolean {
    if (column < 0 || row < 0 || column >= grid.columns || row >= grid.rows) {
      return false;
    }

    return this.opaqueAt(grid, row * grid.columns + column, eye);
  }

  /** Ограничение в диапазоне. */
  private clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
  }

  /**
   * Клетка перекрывает взгляд на высоте глаз.
   *
   * Луч ровно на верхней кромке тоже перекрыт: глаз, поднятый до верха стены
   * или потолка, иначе проскакивал бы поверх неё. Полос у клетки две: у окна
   * это подоконник и перемычка, между ними — проём.
   */
  private opaqueAt(grid: SceneSurfaceGrid, index: number, eye: number): boolean {
    if (this.insideBandOf(grid.sightBottom[index], grid.sightTop[index], eye)) {
      return true;
    }

    return this.insideBandOf(grid.sightBottom2[index], grid.sightTop2[index], eye);
  }

  /** Высота внутри полосы `bottom…top`; пустая полоса не перекрывает. */
  private insideBandOf(bottom: number, top: number, height: number): boolean {
    if (!Number.isFinite(bottom)) {
      return false;
    }

    return height > bottom && height <= top;
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

    return this.clearAny(grid, from, to, origin, index, eye, body, fromFloor, toFloor);
  }

  /**
   * Высота глаз с поправкой на потолок, ипари.
   *
   * Потолок — низ полосы обзора клетки над полом: крыша дома над маршем,
   * настил моста над водой, крона над землёй. Глаз не может быть выше него,
   * иначе луч начинался бы внутри перекрытия и видел бы сквозь крышу. На самом
   * настиле его низ уже под ногами, поэтому ограничение не срабатывает, а на
   * люке полоса снята — глаз выходит на крышу.
   *
   * @param floor Пол под ногами, ипари.
   */
  private eyeHeight(grid: SceneSurfaceGrid, cell: number, height: number, floor: number): number {
    const bottom = grid.sightBottom[cell];
    if (!Number.isFinite(bottom) || bottom <= floor) {
      return height;
    }

    return Math.min(height, bottom - floor);
  }

  /** Глаз внутри полосы исходной клетки: луч не выйдет наружу. */
  private insideBand(grid: SceneSurfaceGrid, cell: number, height: number): boolean {
    return (
      this.strictlyInside(grid.sightBottom[cell], grid.sightTop[cell], height) ||
      this.strictlyInside(grid.sightBottom2[cell], grid.sightTop2[cell], height)
    );
  }

  /** Высота строго внутри полосы `bottom…top`; пустая полоса не перекрывает. */
  private strictlyInside(bottom: number, top: number, height: number): boolean {
    if (!Number.isFinite(bottom)) {
      return false;
    }

    return height > bottom && height < top;
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
  ): boolean {
    const distance = Math.hypot(to[0] - from[0], to[1] - from[1]);
    const step = Math.max(1, grid.cell * SCENE_SURFACE.sightStep);
    const steps = Math.max(1, Math.ceil(distance / step));
    const start = fromFloor + this.eyeHeight(grid, origin, eye, fromFloor);
    const body = this.eyeHeight(grid, target, bodyHeight, toFloor);
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
      this.pushBand(start, t, grid.sightBottom[cell], grid.sightTop[cell], min, max);
      // Вторая полоса — перемычка окна: тело закрывает и она.
      this.pushBand(start, t, grid.sightBottom2[cell], grid.sightTop2[cell], min, max);
    }

    return !this.covered(min, max);
  }

  /** Добавляет в закрытые интервалы полосу клетки, пересчитанную на тело цели. */
  private pushBand(start: number, t: number, bottom: number, top: number, min: number, max: number): void {
    if (!Number.isFinite(bottom)) {
      return;
    }

    const lo = start + (bottom - start) / t;
    if (lo >= max) {
      return;
    }

    this.pushInterval(lo, start + (top - start) / t, min, max);
  }
}
