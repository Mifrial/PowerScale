import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';

/**
 * Минимум элемента, нужный стенам.
 *
 * Подходит и элемент рендера, и элемент черновика: привязка работает до
 * сохранения, когда реальных id ещё нет.
 */
export interface SceneWallElement {
  id: number;
  boardId: number;
  kind: SceneElementKind;
  shape: SceneShape;
  props: SceneElementProps;
}

/** Участок стены между проёмами, единицы сцены вдоль осевой. */
export interface SceneWallSpan {
  from: number;
  to: number;
}

/** Проём, поставленный на стену. */
export interface SceneWallPlacement {
  /** Стена, в которую вставлен проём. */
  wallId: number;
  /** Начало проёма вдоль осевой. */
  offset: number;
  /** Длина проёма вдоль осевой. */
  width: number;
  /** Геометрия проёма: лента той же толщины, что и стена. */
  shape: SceneShape;
}

/** Минимальная длина сегмента и проёма в единицах сцены. */
const MIN_SIZE = 1;

/**
 * Стены-ленты и проёмы в них.
 *
 * Стена — `obstacle` с фигурой `wall`: осевая линия и толщина. Проём —
 * `opening` с такой же фигурой: короткий участок той же осевой. Сервис режет
 * стену на участки между проёмами, привязывает проём к ближайшей стене и
 * пересчитывает его геометрию, когда стена двигается или меняет толщину.
 *
 * Привязка `wallId` — редакторская: при сохранении она срезается, а при
 * загрузке восстанавливается проекцией проёма на стену. Поэтому сервис умеет
 * находить стену и без явного id — по геометрии.
 */
export class SceneWallService {
  /** Стена-лента. */
  isWall(element: SceneWallElement): boolean {
    return element.kind === 'obstacle' && element.shape.type === 'wall';
  }

  /** Проём-лента. */
  isOpening(element: SceneWallElement): boolean {
    return element.kind === 'opening' && element.shape.type === 'wall';
  }

  /** Длина осевой стены или проёма. */
  length(element: SceneWallElement): number {
    const points = this.points(element);

    return points === null ? 0 : this.polylineLength(points);
  }

  /** Точка на осевой по расстоянию от начала. */
  pointAt(element: SceneWallElement, offset: number): ScenePoint | null {
    const points = this.points(element);
    if (points === null || points.length < 2) {
      return null;
    }

    let rest = Math.max(0, offset);
    for (let index = 1; index < points.length; index += 1) {
      const from = points[index - 1];
      const to = points[index];
      const length = Math.hypot(to[0] - from[0], to[1] - from[1]);
      if (rest <= length || index === points.length - 1) {
        const t = length <= 0 ? 0 : Math.min(1, rest / length);

        return [Math.round(from[0] + (to[0] - from[0]) * t), Math.round(from[1] + (to[1] - from[1]) * t)];
      }

      rest -= length;
    }

    return points[points.length - 1];
  }

  /** Проекция точки на осевую: смещение вдоль и расстояние до линии. */
  project(element: SceneWallElement, point: ScenePoint): { offset: number; distance: number } | null {
    const points = this.points(element);
    if (points === null || points.length < 2) {
      return null;
    }

    let best: { offset: number; distance: number } | null = null;
    let passed = 0;
    for (let index = 1; index < points.length; index += 1) {
      const from = points[index - 1];
      const to = points[index];
      const dx = to[0] - from[0];
      const dy = to[1] - from[1];
      const square = dx * dx + dy * dy;
      const length = Math.sqrt(square);
      const t =
        square <= 0 ? 0 : Math.max(0, Math.min(1, ((point[0] - from[0]) * dx + (point[1] - from[1]) * dy) / square));
      const distance = Math.hypot(point[0] - (from[0] + dx * t), point[1] - (from[1] + dy * t));
      if (best === null || distance < best.distance) {
        best = { offset: passed + length * t, distance };
      }

      passed += length;
    }

    return best;
  }

  /**
   * Геометрия участка стены между двумя смещениями.
   *
   * Промежуточные вершины многосегментной стены сохраняются, поэтому изгиб
   * не выпрямляется. Координаты целые: сцена не хранит дробей.
   */
  spanShape(element: SceneWallElement, from: number, to: number): SceneShape | null {
    const points = this.points(element);
    if (points === null || points.length < 2) {
      return null;
    }

    const length = this.polylineLength(points);
    const start = Math.max(0, Math.min(length, Math.round(from)));
    const end = Math.max(0, Math.min(length, Math.round(to)));
    if (end - start < MIN_SIZE) {
      return null;
    }

    const result: ScenePoint[] = [];
    const first = this.pointAt(element, start);
    if (first === null) {
      return null;
    }

    result.push(first);
    let passed = 0;
    for (let index = 1; index < points.length - 1; index += 1) {
      passed += Math.hypot(points[index][0] - points[index - 1][0], points[index][1] - points[index - 1][1]);
      if (passed > start && passed < end) {
        result.push(points[index]);
      }
    }

    const last = this.pointAt(element, end);
    if (last === null) {
      return null;
    }

    result.push(last);

    return { type: 'wall', points: result, width: element.shape.type === 'wall' ? element.shape.width : 1 };
  }

  /** Участки стены, свободные от проёмов. */
  segments(wall: SceneWallElement, openings: readonly SceneWallElement[]): SceneShape[] {
    if (!this.isWall(wall)) {
      return [];
    }

    const length = this.length(wall);
    const result: SceneShape[] = [];
    let cursor = 0;
    for (const span of this.spans(wall, openings)) {
      if (span.from > cursor) {
        const shape = this.spanShape(wall, cursor, span.from);
        if (shape !== null) {
          result.push(shape);
        }
      }

      cursor = Math.max(cursor, span.to);
    }

    if (cursor < length) {
      const shape = this.spanShape(wall, cursor, length);
      if (shape !== null) {
        result.push(shape);
      }
    }

    return result;
  }

  /**
   * Вырезы в стене.
   *
   * Сначала берётся явная привязка `wallId` с её смещением. Проёмы без id
   * (старые сцены, загруженный снимок) привязываются по геометрии: оба конца
   * лежат на этой стене.
   */
  spans(wall: SceneWallElement, openings: readonly SceneWallElement[]): SceneWallSpan[] {
    if (!this.isWall(wall)) {
      return [];
    }

    const length = this.length(wall);
    const thickness = wall.shape.type === 'wall' ? wall.shape.width : 0;
    const result: SceneWallSpan[] = [];
    for (const opening of openings) {
      if (!this.isOpening(opening) || opening.boardId !== wall.boardId) {
        continue;
      }

      const width = this.length(opening);
      if (width < MIN_SIZE) {
        continue;
      }

      let from: number;
      let to: number;
      if (opening.props.wallId === wall.id) {
        from = opening.props.offset ?? 0;
        to = from + width;
      } else {
        const points = this.points(opening);
        if (points === null) {
          continue;
        }

        const start = this.project(wall, points[0]);
        const end = this.project(wall, points[points.length - 1]);
        if (start === null || end === null) {
          continue;
        }

        const limit = (thickness + (opening.shape.type === 'wall' ? opening.shape.width : 0)) / 2;
        if (start.distance > limit || end.distance > limit) {
          continue;
        }

        from = Math.min(start.offset, end.offset);
        to = Math.max(start.offset, end.offset);
      }

      const clipped: SceneWallSpan = {
        from: Math.max(0, Math.round(from)),
        to: Math.min(length, Math.round(to)),
      };
      if (clipped.to - clipped.from >= MIN_SIZE) {
        result.push(clipped);
      }
    }

    return this.merge(result);
  }

  /**
   * Ставит проём на ближайшую стену по протяжке.
   *
   * Протяжка вдоль стены задаёт ширину проёма. Короткий клик получает
   * ширину пресета по центру проекции. Если рядом стены нет — `null`:
   * свободный проём посреди поля не нужен.
   */
  place(
    elements: readonly SceneWallElement[],
    boardId: number,
    from: ScenePoint,
    to: ScenePoint,
    width: number,
    minWidth: number,
    tolerance: number,
  ): SceneWallPlacement | null {
    const middle: ScenePoint = [(from[0] + to[0]) / 2, (from[1] + to[1]) / 2];
    const wall = this.nearestWall(elements, boardId, middle, width, tolerance);
    if (wall === null) {
      return null;
    }

    const length = this.length(wall);
    if (length < MIN_SIZE) {
      return null;
    }

    const start = this.project(wall, from);
    const end = this.project(wall, to);
    let spanFrom = start === null ? 0 : start.offset;
    let spanTo = end === null ? 0 : end.offset;
    if (Math.abs(spanTo - spanFrom) < minWidth) {
      const center = this.project(wall, middle)?.offset ?? spanFrom;
      spanFrom = center - width / 2;
      spanTo = center + width / 2;
    }

    let offset = Math.round(Math.max(0, Math.min(length - MIN_SIZE, Math.min(spanFrom, spanTo))));
    let spanEnd = Math.round(Math.max(offset + MIN_SIZE, Math.min(length, Math.max(spanFrom, spanTo))));
    if (spanEnd - offset < minWidth && offset + width <= length) {
      spanEnd = offset + Math.round(width);
    }

    offset = Math.max(0, Math.min(length - MIN_SIZE, offset));
    spanEnd = Math.max(offset + MIN_SIZE, Math.min(length, spanEnd));
    const shape = this.spanShape(wall, offset, spanEnd);
    if (shape === null) {
      return null;
    }

    return { wallId: wall.id, offset, width: spanEnd - offset, shape };
  }

  /**
   * Привязывает готовый проём к ближайшей стене.
   *
   * Нужна после перетаскивания проёма и после загрузки сцены: в снимке
   * привязки нет, её восстанавливают по геометрии.
   */
  attach(
    elements: readonly SceneWallElement[],
    opening: SceneWallElement,
    tolerance: number,
  ): SceneWallPlacement | null {
    if (!this.isOpening(opening)) {
      return null;
    }

    const points = this.points(opening);
    if (points === null) {
      return null;
    }

    const middle: ScenePoint = [
      (points[0][0] + points[points.length - 1][0]) / 2,
      (points[0][1] + points[points.length - 1][1]) / 2,
    ];
    const width = this.length(opening);
    const wall = this.nearestWall(elements, opening.boardId, middle, width, tolerance);
    if (wall === null) {
      return null;
    }

    const start = this.project(wall, points[0]);
    const end = this.project(wall, points[points.length - 1]);
    if (start === null || end === null) {
      return null;
    }

    const length = this.length(wall);
    const offset = Math.round(Math.max(0, Math.min(length - MIN_SIZE, Math.min(start.offset, end.offset))));
    const spanEnd = Math.round(Math.max(offset + MIN_SIZE, Math.min(length, Math.max(start.offset, end.offset))));
    const shape = this.spanShape(wall, offset, spanEnd);
    if (shape === null) {
      return null;
    }

    return { wallId: wall.id, offset, width: spanEnd - offset, shape };
  }

  /** Восстанавливает привязку всех проёмов по геометрии. */
  attachAll(elements: readonly SceneWallElement[]): void {
    for (const element of elements) {
      if (!this.isOpening(element)) {
        continue;
      }

      const placement = this.attach(elements, element, 0);
      if (placement === null) {
        delete element.props.wallId;
        delete element.props.offset;

        continue;
      }

      element.props.wallId = placement.wallId;
      element.props.offset = placement.offset;
      element.shape = placement.shape;
    }
  }

  /**
   * Пересчитывает проёмы, вставленные в стену.
   *
   * Смещение остаётся прежним, поэтому проём едет вместе со стеной. Если стена
   * стала короче проёма, проём сжимается до её длины.
   */
  sync(elements: readonly SceneWallElement[], wallId: number): void {
    const wall = elements.find((element) => element.id === wallId);
    if (wall === undefined || !this.isWall(wall)) {
      return;
    }

    const length = this.length(wall);
    for (const opening of elements) {
      if (!this.isOpening(opening) || opening.props.wallId !== wallId) {
        continue;
      }

      const width = Math.max(MIN_SIZE, Math.min(this.length(opening), Math.max(MIN_SIZE, length)));
      const offset = Math.round(Math.max(0, Math.min(Math.max(0, length - width), opening.props.offset ?? 0)));
      const shape = this.spanShape(wall, offset, offset + width);
      if (shape === null) {
        continue;
      }

      opening.props.offset = offset;
      opening.shape = shape;
    }
  }

  /** Id проёмов, вставленных в стену. */
  attachedIds(elements: readonly SceneWallElement[], wallId: number): number[] {
    return elements
      .filter((element) => this.isOpening(element) && element.props.wallId === wallId)
      .map((element) => element.id);
  }

  /** Меняет длину проёма вдоль стены, сохраняя его начало. */
  resize(elements: readonly SceneWallElement[], opening: SceneWallElement, width: number): void {
    const wall = elements.find((element) => element.id === opening.props.wallId);
    if (wall === undefined || !this.isWall(wall) || !this.isOpening(opening)) {
      return;
    }

    const length = this.length(wall);
    const offset = Math.round(Math.max(0, Math.min(Math.max(0, length - MIN_SIZE), opening.props.offset ?? 0)));
    const spanEnd = Math.max(offset + MIN_SIZE, Math.min(length, offset + Math.max(MIN_SIZE, Math.round(width))));
    const shape = this.spanShape(wall, offset, spanEnd);
    if (shape === null) {
      return;
    }

    opening.props.offset = offset;
    opening.shape = shape;
  }

  /**
   * Притягивает точку к концу стены, а иначе — к шагу сетки.
   *
   * Так дом собирается без дробей и стены сходятся в углах: конец новой стены
   * цепляется за конец уже нарисованной.
   */
  snapPoint(
    elements: readonly SceneWallElement[],
    boardId: number,
    point: ScenePoint,
    tolerance: number,
    gridStep: number,
  ): ScenePoint {
    let best: { point: ScenePoint; distance: number } | null = null;
    for (const element of elements) {
      if (element.boardId !== boardId || !this.isWall(element)) {
        continue;
      }

      const points = this.points(element);
      if (points === null) {
        continue;
      }

      for (const endpoint of [points[0], points[points.length - 1]]) {
        const distance = Math.hypot(endpoint[0] - point[0], endpoint[1] - point[1]);
        if (distance <= tolerance && (best === null || distance < best.distance)) {
          best = { point: [endpoint[0], endpoint[1]], distance };
        }
      }
    }

    if (best !== null) {
      return best.point;
    }

    if (gridStep > 0) {
      return [Math.round(point[0] / gridStep) * gridStep, Math.round(point[1] / gridStep) * gridStep];
    }

    return [Math.round(point[0]), Math.round(point[1])];
  }

  /** Ближайшая стена, если точка не дальше её полутолщины и допуска. */
  private nearestWall(
    elements: readonly SceneWallElement[],
    boardId: number,
    point: ScenePoint,
    width: number,
    tolerance: number,
  ): SceneWallElement | null {
    let best: { wall: SceneWallElement; distance: number } | null = null;
    for (const element of elements) {
      if (element.boardId !== boardId || !this.isWall(element)) {
        continue;
      }

      const projection = this.project(element, point);
      if (projection === null) {
        continue;
      }

      if (best === null || projection.distance < best.distance) {
        best = { wall: element, distance: projection.distance };
      }
    }

    if (best === null) {
      return null;
    }

    const half = (best.wall.shape.type === 'wall' ? best.wall.shape.width : 0) / 2 + width / 2;
    if (best.distance > half + tolerance) {
      return null;
    }

    return best.wall;
  }

  /** Вершины осевой или null. */
  private points(element: SceneWallElement): ScenePoint[] | null {
    return element.shape.type === 'wall' ? element.shape.points : null;
  }

  /** Длина ломаной. */
  private polylineLength(points: readonly ScenePoint[]): number {
    let length = 0;
    for (let index = 1; index < points.length; index += 1) {
      length += Math.hypot(points[index][0] - points[index - 1][0], points[index][1] - points[index - 1][1]);
    }

    return length;
  }

  /** Объединяет пересекающиеся вырезы, чтобы стена не резалась дважды. */
  private merge(spans: SceneWallSpan[]): SceneWallSpan[] {
    const sorted = [...spans].sort((left, right) => left.from - right.from);
    const result: SceneWallSpan[] = [];
    for (const span of sorted) {
      const last = result[result.length - 1];
      if (last !== undefined && span.from <= last.to) {
        last.to = Math.max(last.to, span.to);

        continue;
      }

      result.push({ ...span });
    }

    return result;
  }
}
