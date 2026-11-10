import type { SceneHandle } from '@/modules/Roleplay/Scene/Dto/SceneHandle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/**
 * Кубические кривые Безье: ручки вершин, разворот в полилинию и деление сегмента.
 *
 * Все смещения ручек — относительно вершины, координаты — в единицах сцены.
 */
export class SceneCurveService {
  /** Нулевые ручки: сегмент остаётся прямым. */
  zeroHandle(): SceneHandle {
    return { in: [0, 0], out: [0, 0] };
  }

  /** Ручки под размер контура; недостающие заполняются нулями. */
  normalizeHandles(points: ScenePoint[], handles?: SceneHandle[]): SceneHandle[] {
    return points.map((_, index) => {
      const handle = handles?.[index];
      if (handle === undefined) {
        return this.zeroHandle();
      }

      return {
        in: [handle.in[0], handle.in[1]],
        out: [handle.out[0], handle.out[1]],
      };
    });
  }

  /** Есть ли у контура хотя бы одна ненулевая ручка. */
  isCurved(handles?: SceneHandle[]): boolean {
    return (handles ?? []).some((handle) => this.handleLength(handle) > 0);
  }

  /** Плавные ручки по соседним точкам; концы остаются прямыми. */
  autoHandles(points: ScenePoint[]): SceneHandle[] {
    const last = points.length - 1;
    if (points.length < 3) {
      return points.map(() => this.zeroHandle());
    }

    return points.map((point, index) => {
      if (index === 0 || index === last) {
        return this.zeroHandle();
      }

      const previous = points[index - 1];
      const next = points[index + 1];
      const tangentX = next[0] - previous[0];
      const tangentY = next[1] - previous[1];
      const length = Math.hypot(tangentX, tangentY);
      if (length === 0) {
        return this.zeroHandle();
      }

      const unitX = tangentX / length;
      const unitY = tangentY / length;
      const before = Math.hypot(point[0] - previous[0], point[1] - previous[1]) / 3;
      const after = Math.hypot(next[0] - point[0], next[1] - point[1]) / 3;

      return {
        in: [-Math.round(unitX * before), -Math.round(unitY * before)],
        out: [Math.round(unitX * after), Math.round(unitY * after)],
      };
    });
  }

  /** Точка кубической кривой Безье. */
  cubicPoint(
    start: ScenePoint,
    controlOne: ScenePoint,
    controlTwo: ScenePoint,
    end: ScenePoint,
    t: number,
  ): ScenePoint {
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

  /** Разворачивает открытую кривую в полилинию. */
  flatten(points: ScenePoint[], handles: SceneHandle[], samples = 12): ScenePoint[] {
    if (points.length < 2) {
      return points.map((point): ScenePoint => [point[0], point[1]]);
    }

    const source = this.normalizeHandles(points, handles);
    const flat: ScenePoint[] = [];
    for (let index = 1; index < points.length; index += 1) {
      const start = points[index - 1];
      const end = points[index];
      const controlOne: ScenePoint = [start[0] + source[index - 1].out[0], start[1] + source[index - 1].out[1]];
      const controlTwo: ScenePoint = [end[0] + source[index].in[0], end[1] + source[index].in[1]];
      for (let step = index === 1 ? 0 : 1; step <= samples; step += 1) {
        flat.push(this.cubicPoint(start, controlOne, controlTwo, end, step / samples));
      }
    }

    return flat;
  }

  /**
   * Разворачивает замкнутый контур в полилинию.
   *
   * Сегмент `i → i+1` использует `out` вершины `i` и `in` вершины `i+1`;
   * последний сегмент замыкается на первую вершину.
   */
  flattenContour(points: ScenePoint[], handles: SceneHandle[], samples = 12): ScenePoint[] {
    if (points.length < 3) {
      return points.map((point): ScenePoint => [point[0], point[1]]);
    }

    if (!this.isCurved(handles)) {
      return points.map((point): ScenePoint => [point[0], point[1]]);
    }

    const source = this.normalizeHandles(points, handles);
    const flat: ScenePoint[] = [];
    for (let index = 0; index < points.length; index += 1) {
      const next = (index + 1) % points.length;
      const start = points[index];
      const end = points[next];
      const controlOne: ScenePoint = [start[0] + source[index].out[0], start[1] + source[index].out[1]];
      const controlTwo: ScenePoint = [end[0] + source[next].in[0], end[1] + source[next].in[1]];
      for (let step = 0; step < samples; step += 1) {
        flat.push(this.cubicPoint(start, controlOne, controlTwo, end, step / samples));
      }
    }

    return flat;
  }

  /** Направление сегмента в вершине: для ручек-заглушек и нормали. */
  tangent(points: ScenePoint[], handles: SceneHandle[], index: number, closed = false): ScenePoint {
    const point = points[index];
    const handle = handles[index];
    if (handle !== undefined && this.handleLength(handle) > 0) {
      const direction: ScenePoint = [handle.out[0] - handle.in[0], handle.out[1] - handle.in[1]];

      return this.normalize(direction);
    }

    const last = points.length - 1;
    if (closed) {
      const previous = points[(index - 1 + points.length) % points.length];
      const next = points[(index + 1) % points.length];

      return this.normalize([next[0] - previous[0], next[1] - previous[1]]);
    }

    if (index === 0) {
      return this.normalize([points[1][0] - point[0], points[1][1] - point[1]]);
    }

    if (index === last) {
      return this.normalize([point[0] - points[last - 1][0], point[1] - points[last - 1][1]]);
    }

    const previous = points[index - 1];
    const next = points[index + 1];

    return this.normalize([next[0] - previous[0], next[1] - previous[1]]);
  }

  /** Видимые ручки вершины: настоящие, а при их отсутствии — направляющие-заглушки. */
  displayHandles(
    points: ScenePoint[],
    handles: SceneHandle[],
    index: number,
    stub: number,
    closed = false,
  ): { in: ScenePoint; out: ScenePoint; inIsStub: boolean; outIsStub: boolean } {
    const point = points[index];
    const handle = handles[index] ?? this.zeroHandle();
    const direction = this.tangent(points, handles, index, closed);
    const inIsStub = Math.hypot(handle.in[0], handle.in[1]) <= 1;
    const outIsStub = Math.hypot(handle.out[0], handle.out[1]) <= 1;

    return {
      in: inIsStub
        ? [point[0] - direction[0] * stub, point[1] - direction[1] * stub]
        : [point[0] + handle.in[0], point[1] + handle.in[1]],
      out: outIsStub
        ? [point[0] + direction[0] * stub, point[1] + direction[1] * stub]
        : [point[0] + handle.out[0], point[1] + handle.out[1]],
      inIsStub,
      outIsStub,
    };
  }

  /** Делит кубический сегмент на две части, сохраняя форму. */
  splitCubic(
    start: ScenePoint,
    controlOne: ScenePoint,
    controlTwo: ScenePoint,
    end: ScenePoint,
    t: number,
  ): {
    left: [ScenePoint, ScenePoint, ScenePoint, ScenePoint];
    right: [ScenePoint, ScenePoint, ScenePoint, ScenePoint];
  } {
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

  /** Ближайшая точка на кубическом сегменте: параметр `t`, точка и расстояние. */
  nearestOnCubic(
    point: ScenePoint,
    start: ScenePoint,
    controlOne: ScenePoint,
    controlTwo: ScenePoint,
    end: ScenePoint,
    steps = 24,
  ): { t: number; point: ScenePoint; distance: number } {
    let best = { t: 0, point: start, distance: this.distance(point, start) };
    for (let step = 1; step <= steps; step += 1) {
      const t = step / steps;
      const sample = this.cubicPoint(start, controlOne, controlTwo, end, t);
      const distance = this.distance(point, sample);
      if (distance < best.distance) {
        best = { t, point: sample, distance };
      }
    }

    return best;
  }

  /** Расстояние между точками. */
  distance(from: ScenePoint, to: ScenePoint): number {
    return Math.hypot(to[0] - from[0], to[1] - from[1]);
  }

  /** Длина ручки. */
  handleLength(handle: SceneHandle): number {
    return Math.hypot(handle.in[0], handle.in[1]) + Math.hypot(handle.out[0], handle.out[1]);
  }

  /** Единичный вектор; нулевой вектор остаётся нулевым. */
  private normalize(vector: ScenePoint): ScenePoint {
    const length = Math.hypot(vector[0], vector[1]);
    if (length === 0) {
      return [0, 0];
    }

    return [vector[0] / length, vector[1] / length];
  }

  /** Линейная интерполяция точек. */
  private lerp(from: ScenePoint, to: ScenePoint, t: number): ScenePoint {
    return [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t];
  }
}
