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

/** Стык стен: точка, где сходятся две и более ленты. */
export interface SceneWallJoint {
  /** Вершина стыка. */
  point: ScenePoint;
  /** Наибольшая толщина среди сходящихся стен. */
  width: number;
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
    // Оба конца проёма спроецировались в одну точку — он не на этой стене, а
    // в разрыве между двумя её кусками. Привязка схлопнула бы проём в единицу
    // длины и потеряла бы его при загрузке, поэтому оставляем его свободным.
    const from = Math.min(start.offset, end.offset);
    const to = Math.max(start.offset, end.offset);
    if (to - from < MIN_SIZE) {
      return null;
    }

    const offset = Math.round(Math.max(0, Math.min(length - MIN_SIZE, from)));
    const spanEnd = Math.round(Math.max(offset + MIN_SIZE, Math.min(length, to)));
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

  /** Вершины осевой стены; пусто у не-стены. */
  vertices(element: SceneWallElement): ScenePoint[] {
    return this.points(element) ?? [];
  }

  /**
   * Сваривает стены в сеть.
   *
   * Сначала близкие вершины стягиваются в одну точку, затем конец одной стены,
   * лежащий на теле другой, вставляется в неё коллинеарной вершиной. Длина
   * стены-хозяина не меняется, поэтому смещения проёмов остаются прежними.
   *
   * Возвращает id стен, которые изменились.
   */
  weld(elements: readonly SceneWallElement[], boardId: number, tolerance: number): number[] {
    const walls = elements.filter(
      (element) => element.boardId === boardId && this.isWall(element) && element.shape.type === 'wall',
    );
    if (walls.length === 0) {
      return [];
    }

    const affected = new Set<number>();
    const limit = Math.max(0, tolerance);
    const known: ScenePoint[] = [];

    // 1. Близкие вершины — одна точка. Порядок по id делает результат
    //    устойчивым: вторая вершина приходит к первой, а не наоборот.
    for (const wall of walls) {
      if (wall.shape.type !== 'wall') {
        continue;
      }

      let changed = false;
      const points = wall.shape.points.map((point) => {
        let next: ScenePoint = point;
        if (limit > 0) {
          let best: { point: ScenePoint; distance: number } | null = null;
          for (const candidate of known) {
            const distance = Math.hypot(candidate[0] - point[0], candidate[1] - point[1]);
            if (distance > 0 && distance <= limit && (best === null || distance < best.distance)) {
              best = { point: candidate, distance };
            }
          }

          if (best !== null) {
            next = best.point;
          }
        }

        if (next[0] !== point[0] || next[1] !== point[1]) {
          changed = true;
        }

        known.push(next);

        return next;
      });

      if (changed) {
        wall.shape = { ...wall.shape, points: this.dedupe(points) };
        affected.add(wall.id);
      }
    }

    // 2. Конец или угол одной стены на теле другой — общая вершина.
    for (const wall of walls) {
      if (wall.shape.type !== 'wall') {
        continue;
      }

      const points: ScenePoint[] = wall.shape.points.map((point) => [point[0], point[1]]);
      let changed = false;
      for (const point of points) {
        for (const host of walls) {
          if (host.id === wall.id || host.shape.type !== 'wall' || this.hasVertex(host, point)) {
            continue;
          }

          const projection = this.insertVertex(host, point, limit);
          if (projection === null) {
            continue;
          }

          point[0] = projection[0];
          point[1] = projection[1];
          changed = true;
          affected.add(host.id);
        }
      }

      if (changed) {
        wall.shape = { ...wall.shape, points: this.dedupe(points) };
        affected.add(wall.id);
      }
    }

    for (const wallId of affected) {
      this.sync(elements, wallId);
    }

    return [...affected];
  }

  /**
   * Стыки, которым нужен уголок.
   *
   * Вершина одной стены — её собственный угол: лента сама сходится митрой.
   * Вершина, общая для двух и более лент, и шов замкнутого кольца — это стык,
   * где два прямоугольных торца оставляют щель.
   */
  joints(elements: readonly SceneWallElement[], boardId: number): SceneWallJoint[] {
    const joints = new Map<string, { point: ScenePoint; width: number; count: number }>();
    for (const element of elements) {
      if (element.boardId !== boardId || !this.isWall(element) || element.shape.type !== 'wall') {
        continue;
      }

      const points = element.shape.points;
      if (points.length < 2) {
        continue;
      }

      const closed = this.isClosed(points);
      const unique = closed ? points.slice(0, -1) : points;
      const add = (point: ScenePoint): void => {
        const key = `${point[0]},${point[1]}`;
        const joint = joints.get(key) ?? { point, width: 0, count: 0 };
        joint.width = Math.max(joint.width, element.shape.type === 'wall' ? element.shape.width : 0);
        joint.count += 1;
        joints.set(key, joint);
      };

      for (const point of unique) {
        add(point);
      }

      // Шов кольца: первая и последняя точки — одна вершина, но торец у неё
      // один, поэтому считаем её дважды.
      if (closed) {
        add(points[0]);
      }
    }

    return [...joints.values()]
      .filter((joint) => joint.count >= 2)
      .map((joint) => ({ point: joint.point, width: joint.width }));
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
    // Вершина важнее тела стены: ветку начинают от узла, а не от случайной
    // точки на полосе. Поэтому сначала ищем вершины, и только потом проекцию.
    let vertex: { point: ScenePoint; distance: number } | null = null;
    for (const element of elements) {
      if (element.boardId !== boardId || !this.isWall(element)) {
        continue;
      }

      const points = this.points(element);
      if (points === null) {
        continue;
      }

      for (const candidate of points) {
        const distance = Math.hypot(candidate[0] - point[0], candidate[1] - point[1]);
        if (distance <= tolerance && (vertex === null || distance < vertex.distance)) {
          vertex = { point: [candidate[0], candidate[1]], distance };
        }
      }
    }

    if (vertex !== null) {
      return vertex.point;
    }

    let segment: { point: ScenePoint; distance: number } | null = null;
    for (const element of elements) {
      if (element.boardId !== boardId || !this.isWall(element)) {
        continue;
      }

      const points = this.points(element);
      if (points === null) {
        continue;
      }

      for (let index = 1; index < points.length; index += 1) {
        const projected = this.projectOnSegment(point, points[index - 1], points[index]);
        if (projected === null) {
          continue;
        }

        if (projected.distance <= tolerance && (segment === null || projected.distance < segment.distance)) {
          segment = projected;
        }
      }
    }

    if (segment !== null) {
      return segment.point;
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

  /** Замкнутое кольцо: последняя точка совпадает с первой. */
  private isClosed(points: readonly ScenePoint[]): boolean {
    if (points.length < 3) {
      return false;
    }

    const first = points[0];
    const last = points[points.length - 1];

    return first[0] === last[0] && first[1] === last[1];
  }

  /** Убирает совпавшие подряд точки, но не рвёт шов кольца. */
  private dedupe(points: readonly ScenePoint[]): ScenePoint[] {
    const result: ScenePoint[] = [];
    for (const point of points) {
      const last = result[result.length - 1];
      if (last === undefined || last[0] !== point[0] || last[1] !== point[1]) {
        result.push([point[0], point[1]]);
      }
    }

    return result.length >= 2 ? result : points.map((point) => [point[0], point[1]]);
  }

  /** Точка уже есть среди вершин стены. */
  private hasVertex(element: SceneWallElement, point: ScenePoint): boolean {
    const points = this.points(element);

    return points !== null && points.some((item) => item[0] === point[0] && item[1] === point[1]);
  }

  /**
   * Вставляет точку в тело стены коллинеарной вершиной.
   *
   * Возвращает округлённую проекцию или null, если точка не лежит на полосе.
   * Длина ломаной не меняется: точка стоит на её отрезке.
   */
  private insertVertex(element: SceneWallElement, point: ScenePoint, tolerance: number): ScenePoint | null {
    if (element.shape.type !== 'wall') {
      return null;
    }

    const points = element.shape.points;
    let best: { index: number; point: ScenePoint; distance: number } | null = null;
    for (let index = 1; index < points.length; index += 1) {
      const projected = this.projectOnSegment(point, points[index - 1], points[index]);
      if (projected === null) {
        continue;
      }

      if (best === null || projected.distance < best.distance) {
        best = { index, point: projected.point, distance: projected.distance };
      }
    }

    if (best === null || best.distance > tolerance || this.hasVertex(element, best.point)) {
      return null;
    }

    element.shape = {
      ...element.shape,
      points: [...points.slice(0, best.index), best.point, ...points.slice(best.index)],
    };

    return best.point;
  }

  /** Ближайшая точка отрезка; координаты целые, как и вся сцена. */
  private projectOnSegment(
    point: ScenePoint,
    from: ScenePoint,
    to: ScenePoint,
  ): { point: ScenePoint; distance: number } | null {
    const dx = to[0] - from[0];
    const dy = to[1] - from[1];
    const square = dx * dx + dy * dy;
    if (square <= 0) {
      return null;
    }

    const t = Math.max(0, Math.min(1, ((point[0] - from[0]) * dx + (point[1] - from[1]) * dy) / square));
    const projected: ScenePoint = [Math.round(from[0] + dx * t), Math.round(from[1] + dy * t)];

    return { point: projected, distance: Math.hypot(point[0] - projected[0], point[1] - projected[1]) };
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
