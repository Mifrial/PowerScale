import type { MapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/**
 * Плоская геометрия карты: длины, площади, попадания и расстояния.
 */
export class MapGeometryService {
  polylineLength(points: MapPoint[]): number {
    let length = 0;
    for (let index = 1; index < points.length; index += 1) {
      length += this.distance(points[index - 1], points[index]);
    }

    return length;
  }

  distance(left: MapPoint, right: MapPoint): number {
    return Math.hypot(left[0] - right[0], left[1] - right[1]);
  }

  polygonArea(polygon: MapPoint[]): number {
    if (polygon.length < 3) {
      return 0;
    }

    let twiceArea = 0;
    for (let index = 0; index < polygon.length; index += 1) {
      const current = polygon[index];
      const next = polygon[(index + 1) % polygon.length];
      twiceArea += current[0] * next[1] - next[0] * current[1];
    }

    return Math.abs(twiceArea) / 2;
  }

  polygonCentroid(polygon: MapPoint[]): MapPoint {
    if (polygon.length === 0) {
      return [0, 0];
    }

    let twiceArea = 0;
    let centroidX = 0;
    let centroidY = 0;
    for (let index = 0; index < polygon.length; index += 1) {
      const current = polygon[index];
      const next = polygon[(index + 1) % polygon.length];
      const cross = current[0] * next[1] - next[0] * current[1];
      twiceArea += cross;
      centroidX += (current[0] + next[0]) * cross;
      centroidY += (current[1] + next[1]) * cross;
    }

    if (Math.abs(twiceArea) < 1e-9) {
      const sum = polygon.reduce((total, point) => [total[0] + point[0], total[1] + point[1]] as MapPoint, [0, 0]);

      return [sum[0] / polygon.length, sum[1] / polygon.length];
    }

    const factor = 1 / (3 * twiceArea);

    return [centroidX * factor, centroidY * factor];
  }

  pointInPolygon(point: MapPoint, polygon: MapPoint[]): boolean {
    if (polygon.length < 3) {
      return false;
    }

    let isInside = false;
    for (let index = 0, previous = polygon.length - 1; index < polygon.length; previous = index, index += 1) {
      const currentPoint = polygon[index];
      const previousPoint = polygon[previous];
      const intersects =
        currentPoint[1] > point[1] !== previousPoint[1] > point[1] &&
        point[0] <
          ((previousPoint[0] - currentPoint[0]) * (point[1] - currentPoint[1])) / (previousPoint[1] - currentPoint[1]) +
            currentPoint[0];
      if (intersects) {
        isInside = !isInside;
      }
    }

    return isInside;
  }

  distanceToSegment(point: MapPoint, start: MapPoint, end: MapPoint): number {
    const segmentX = end[0] - start[0];
    const segmentY = end[1] - start[1];
    const lengthSquared = segmentX * segmentX + segmentY * segmentY;
    if (lengthSquared === 0) {
      return this.distance(point, start);
    }

    const projection = ((point[0] - start[0]) * segmentX + (point[1] - start[1]) * segmentY) / lengthSquared;
    const clamped = Math.min(1, Math.max(0, projection));

    return this.distance(point, [start[0] + segmentX * clamped, start[1] + segmentY * clamped]);
  }

  distanceToPolyline(point: MapPoint, polyline: MapPoint[]): number {
    if (polyline.length === 0) {
      return Number.POSITIVE_INFINITY;
    }

    if (polyline.length === 1) {
      return this.distance(point, polyline[0]);
    }

    let nearest = Number.POSITIVE_INFINITY;
    for (let index = 1; index < polyline.length; index += 1) {
      nearest = Math.min(nearest, this.distanceToSegment(point, polyline[index - 1], polyline[index]));
    }

    return nearest;
  }

  /** Расстояние до осевой линии за вычетом половины ширины ленты. */
  distanceToRibbon(point: MapPoint, points: MapPoint[], widths: number[]): number {
    if (points.length < 2 || widths.length !== points.length) {
      return Number.POSITIVE_INFINITY;
    }

    let nearest = Number.POSITIVE_INFINITY;
    for (let index = 1; index < points.length; index += 1) {
      const halfWidth = Math.max(widths[index - 1], widths[index]) / 2;
      const distance = this.distanceToSegment(point, points[index - 1], points[index]) - halfWidth;
      nearest = Math.min(nearest, Math.max(0, distance));
    }

    return nearest;
  }

  /** Разворачивает ленту с ручками Безье в полилинию с ширинами. */
  flattenLine(
    points: MapPoint[],
    handles: MapHandle[],
    widths: number[],
    samples = 16,
  ): { points: MapPoint[]; widths: number[] } {
    if (points.length < 2 || widths.length !== points.length) {
      return { points, widths };
    }

    const steps = Math.max(1, samples);
    const sourceHandles = handles ?? [];
    const flatPoints: MapPoint[] = [];
    const flatWidths: number[] = [];
    for (let index = 1; index < points.length; index += 1) {
      const start = points[index - 1];
      const end = points[index];
      const startOut = sourceHandles[index - 1]?.out ?? [0, 0];
      const endIn = sourceHandles[index]?.in ?? [0, 0];
      const controlOne: MapPoint = [start[0] + startOut[0], start[1] + startOut[1]];
      const controlTwo: MapPoint = [end[0] + endIn[0], end[1] + endIn[1]];
      const startWidth = widths[index - 1];
      const endWidth = widths[index];
      for (let step = index === 1 ? 0 : 1; step <= steps; step += 1) {
        const t = step / steps;
        flatPoints.push(this.cubicPoint(start, controlOne, controlTwo, end, t));
        flatWidths.push(startWidth + (endWidth - startWidth) * t);
      }
    }

    return { points: flatPoints, widths: flatWidths };
  }

  /** Точка кубической кривой Безье. */
  cubicPoint(start: MapPoint, controlOne: MapPoint, controlTwo: MapPoint, end: MapPoint, t: number): MapPoint {
    const inverse = 1 - t;
    const a = inverse * inverse * inverse;
    const b = 3 * inverse * inverse * t;
    const c = 3 * inverse * t * t;
    const d = t * t * t;

    return [
      a * start[0] + b * controlOne[0] + c * controlTwo[0] + d * end[0],
      a * start[1] + b * controlOne[1] + c * controlTwo[1] + d * end[1],
    ];
  }

  /**
   * Разворачивает замкнутый контур области в полилинию.
   *
   * Сегмент `i → i+1` использует `out` вершины `i` и `in` вершины `i+1`;
   * последний сегмент замыкается на первую вершину. Повторной первой точки нет.
   */
  flattenContour(path: MapPoint[], handles: MapHandle[], samples = 12): MapPoint[] {
    if (path.length < 3) return path;

    const source = handles ?? [];
    const curved = source.some((handle) => this.handleLength(handle) > 0);
    if (!curved) return path;

    const steps = Math.max(1, samples);
    const flat: MapPoint[] = [];
    for (let index = 0; index < path.length; index += 1) {
      const next = (index + 1) % path.length;
      const start = path[index];
      const end = path[next];
      const startOut = source[index]?.out ?? [0, 0];
      const endIn = source[next]?.in ?? [0, 0];
      const controlOne: MapPoint = [start[0] + startOut[0], start[1] + startOut[1]];
      const controlTwo: MapPoint = [end[0] + endIn[0], end[1] + endIn[1]];
      for (let step = 0; step < steps; step += 1) {
        flat.push(this.cubicPoint(start, controlOne, controlTwo, end, step / steps));
      }
    }

    return flat;
  }

  /** Касательная в вершине замкнутого контура. */
  areaTangent(path: MapPoint[], handles: MapHandle[], index: number): MapPoint {
    const point = path[index];
    if (!point) return [1, 0];

    const handle = handles[index];
    if (handle && this.handleLength(handle) > 0) {
      const direction: MapPoint = [handle.out[0] - handle.in[0], handle.out[1] - handle.in[1]];
      const length = Math.hypot(direction[0], direction[1]);
      if (length > 0) return [direction[0] / length, direction[1] / length];
    }

    const previous = path[(index - 1 + path.length) % path.length];
    const next = path[(index + 1) % path.length];

    return this.normalize([next[0] - previous[0], next[1] - previous[1]]);
  }

  /** Видимые ручки Безье вершины контура; при отсутствии — направляющие-заглушки. */
  areaDisplayHandles(
    path: MapPoint[],
    handles: MapHandle[],
    index: number,
    stub: number,
  ): { in: MapPoint; out: MapPoint; inIsStub: boolean; outIsStub: boolean } {
    const point = path[index];
    const handle = handles[index] ?? { in: [0, 0] as MapPoint, out: [0, 0] as MapPoint };
    const tangent = this.areaTangent(path, handles, index);
    const inIsStub = Math.hypot(handle.in[0], handle.in[1]) <= 1;
    const outIsStub = Math.hypot(handle.out[0], handle.out[1]) <= 1;

    return {
      in: inIsStub
        ? [point[0] - tangent[0] * stub, point[1] - tangent[1] * stub]
        : [point[0] + handle.in[0], point[1] + handle.in[1]],
      out: outIsStub
        ? [point[0] + tangent[0] * stub, point[1] + tangent[1] * stub]
        : [point[0] + handle.out[0], point[1] + handle.out[1]],
      inIsStub,
      outIsStub,
    };
  }

  /** Делит кубический сегмент на две части, сохраняя форму. */
  splitCubic(
    start: MapPoint,
    controlOne: MapPoint,
    controlTwo: MapPoint,
    end: MapPoint,
    t: number,
  ): { left: [MapPoint, MapPoint, MapPoint, MapPoint]; right: [MapPoint, MapPoint, MapPoint, MapPoint] } {
    const first = this.lerp(start, controlOne, t);
    const middle = this.lerp(controlOne, controlTwo, t);
    const last = this.lerp(controlTwo, end, t);
    const firstMiddle = this.lerp(first, middle, t);
    const middleLast = this.lerp(middle, last, t);
    const split = this.lerp(firstMiddle, middleLast, t);

    return {
      left: [start, first, firstMiddle, split],
      right: [split, middleLast, last, end],
    };
  }

  /** Касательная в узле ленты с учётом ручек. */
  lineTangent(points: MapPoint[], handles: MapHandle[], index: number): MapPoint {
    if (points.length < 2) {
      return [1, 0];
    }

    const current = points[index];
    const handle = handles[index];
    if (index === 0) {
      if (handle && (handle.out[0] !== 0 || handle.out[1] !== 0)) {
        return this.normalize(handle.out);
      }

      return this.normalize([points[1][0] - current[0], points[1][1] - current[1]]);
    }

    if (index === points.length - 1) {
      if (handle && (handle.in[0] !== 0 || handle.in[1] !== 0)) {
        return this.normalize([-handle.in[0], -handle.in[1]]);
      }

      const previous = points[index - 1];

      return this.normalize([current[0] - previous[0], current[1] - previous[1]]);
    }

    if (handle && (handle.out[0] !== 0 || handle.out[1] !== 0 || handle.in[0] !== 0 || handle.in[1] !== 0)) {
      return this.normalize([handle.out[0] - handle.in[0], handle.out[1] - handle.in[1]]);
    }

    const previous = points[index - 1];
    const next = points[index + 1];

    return this.normalize([next[0] - previous[0], next[1] - previous[1]]);
  }

  /** Нормаль в узле ленты. */
  lineNormal(points: MapPoint[], handles: MapHandle[], index: number): MapPoint {
    const tangent = this.lineTangent(points, handles, index);

    return [-tangent[1], tangent[0]];
  }

  /** Положение ручки ширины для узла. */
  widthHandlePoint(
    points: MapPoint[],
    handles: MapHandle[],
    widths: number[],
    index: number,
    offset: number,
  ): MapPoint {
    const point = points[index];
    const normal = this.lineNormal(points, handles, index);
    const distance = Math.max(1, widths[index] ?? 1) / 2 + offset;

    return [point[0] + normal[0] * distance, point[1] + normal[1] * distance];
  }

  /**
   * Видимые ручки Безье узла: настоящие, а при их отсутствии — направляющие-заглушки.
   * Заглушки нужны, чтобы прямую ленту можно было изогнуть без поиска невидимой ручки.
   */
  lineDisplayHandles(
    points: MapPoint[],
    handles: MapHandle[],
    index: number,
    stub: number,
  ): { in: MapPoint; out: MapPoint; inIsStub: boolean; outIsStub: boolean } {
    const point = points[index];
    const handle = handles[index] ?? { in: [0, 0] as MapPoint, out: [0, 0] as MapPoint };
    const inLength = Math.hypot(handle.in[0], handle.in[1]);
    const outLength = Math.hypot(handle.out[0], handle.out[1]);
    const tangent = this.lineTangent(points, handles, index);
    const inIsStub = inLength <= 1;
    const outIsStub = outLength <= 1;

    return {
      in: inIsStub
        ? [point[0] - tangent[0] * stub, point[1] - tangent[1] * stub]
        : [point[0] + handle.in[0], point[1] + handle.in[1]],
      out: outIsStub
        ? [point[0] + tangent[0] * stub, point[1] + tangent[1] * stub]
        : [point[0] + handle.out[0], point[1] + handle.out[1]],
      inIsStub,
      outIsStub,
    };
  }

  /** Пересекаются ли два отрезка, включая касание концом. */
  segmentsIntersect(leftStart: MapPoint, leftEnd: MapPoint, rightStart: MapPoint, rightEnd: MapPoint): boolean {
    const leftToRightStart = this.cross(leftStart, leftEnd, rightStart);
    const leftToRightEnd = this.cross(leftStart, leftEnd, rightEnd);
    const rightToLeftStart = this.cross(rightStart, rightEnd, leftStart);
    const rightToLeftEnd = this.cross(rightStart, rightEnd, leftEnd);

    if (
      ((leftToRightStart > 0 && leftToRightEnd < 0) || (leftToRightStart < 0 && leftToRightEnd > 0)) &&
      ((rightToLeftStart > 0 && rightToLeftEnd < 0) || (rightToLeftStart < 0 && rightToLeftEnd > 0))
    ) {
      return true;
    }

    return (
      (leftToRightStart === 0 && this.isOnSegment(leftStart, leftEnd, rightStart)) ||
      (leftToRightEnd === 0 && this.isOnSegment(leftStart, leftEnd, rightEnd)) ||
      (rightToLeftStart === 0 && this.isOnSegment(rightStart, rightEnd, leftStart)) ||
      (rightToLeftEnd === 0 && this.isOnSegment(rightStart, rightEnd, leftEnd))
    );
  }

  /**
   * Упрощает ломаную по алгоритму Дугласа — Пекера.
   *
   * Нужен только для отрисовки маршрута: цифры расстояния и стоимости
   * остаются по исходной сетке.
   */
  simplifyPath(points: MapPoint[], tolerance: number): MapPoint[] {
    if (points.length <= 2 || tolerance <= 0) {
      return points.map((point) => [point[0], point[1]]);
    }

    const limit = tolerance * tolerance;
    const keep = new Array<boolean>(points.length).fill(false);
    keep[0] = true;
    keep[points.length - 1] = true;
    const stack: [number, number][] = [[0, points.length - 1]];
    while (stack.length > 0) {
      const [first, last] = stack.pop() as [number, number];
      let farthest = limit;
      let index = -1;
      for (let candidate = first + 1; candidate < last; candidate += 1) {
        const distance = this.segmentDistanceSquared(points[candidate], points[first], points[last]);
        if (distance > farthest) {
          farthest = distance;
          index = candidate;
        }
      }
      if (index >= 0) {
        keep[index] = true;
        stack.push([first, index], [index, last]);
      }
    }

    return points.filter((_, index) => keep[index]).map((point) => [point[0], point[1]]);
  }

  /**
   * Сглаживает ломаную схемой Чайкина; концы остаются на месте.
   */
  smoothPath(points: MapPoint[], iterations = 2): MapPoint[] {
    if (points.length < 3) {
      return points.map((point) => [point[0], point[1]]);
    }

    let result = points.map((point) => [point[0], point[1]] as MapPoint);
    for (let iteration = 0; iteration < iterations; iteration += 1) {
      const next: MapPoint[] = [result[0]];
      for (let index = 0; index < result.length - 1; index += 1) {
        next.push(this.lerp(result[index], result[index + 1], 0.25));
        next.push(this.lerp(result[index], result[index + 1], 0.75));
      }
      next.push(result[result.length - 1]);
      result = next;
    }

    return result;
  }

  /** Размещения варианта: id узла → точка. */
  placementPoints(
    nodes: { id: number; placements: { variantId: number; x: number; y: number; isPresent: boolean }[] }[],
    variantId: number,
  ): Record<number, MapPoint> {
    const points: Record<number, MapPoint> = {};
    for (const node of nodes) {
      const placement = node.placements.find((candidate) => candidate.variantId === variantId && candidate.isPresent);
      if (placement) {
        points[node.id] = [placement.x, placement.y];
      }
    }

    return points;
  }

  private lerp(start: MapPoint, end: MapPoint, t: number): MapPoint {
    return [start[0] + (end[0] - start[0]) * t, start[1] + (end[1] - start[1]) * t];
  }

  private segmentDistanceSquared(point: MapPoint, start: MapPoint, end: MapPoint): number {
    const dx = end[0] - start[0];
    const dy = end[1] - start[1];
    const lengthSquared = dx * dx + dy * dy;
    if (lengthSquared === 0) {
      return (point[0] - start[0]) ** 2 + (point[1] - start[1]) ** 2;
    }
    const t = Math.min(1, Math.max(0, ((point[0] - start[0]) * dx + (point[1] - start[1]) * dy) / lengthSquared));
    const projectionX = start[0] + t * dx;
    const projectionY = start[1] + t * dy;

    return (point[0] - projectionX) ** 2 + (point[1] - projectionY) ** 2;
  }

  private handleLength(handle: MapHandle | undefined): number {
    if (!handle) return 0;

    return Math.hypot(handle.in[0], handle.in[1]) + Math.hypot(handle.out[0], handle.out[1]);
  }

  private normalize(vector: MapPoint): MapPoint {
    const length = Math.hypot(vector[0], vector[1]);

    return length === 0 ? [1, 0] : [vector[0] / length, vector[1] / length];
  }

  private cross(start: MapPoint, end: MapPoint, point: MapPoint): number {
    return (end[0] - start[0]) * (point[1] - start[1]) - (end[1] - start[1]) * (point[0] - start[0]);
  }

  private isOnSegment(start: MapPoint, end: MapPoint, point: MapPoint): boolean {
    return (
      point[0] <= Math.max(start[0], end[0]) &&
      point[0] >= Math.min(start[0], end[0]) &&
      point[1] <= Math.max(start[1], end[1]) &&
      point[1] >= Math.min(start[1], end[1])
    );
  }
}
