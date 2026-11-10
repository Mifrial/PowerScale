import type { SceneHandle } from '@/modules/Roleplay/Scene/Dto/SceneHandle';
import type { SceneCurveSample } from '@/modules/Roleplay/Scene/Dto/SceneCurveSample';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Глубина рекурсии разбиения: страховка от вырожденных кривых. */
const MAX_SUBDIVISION_DEPTH = 10;

/** Допуск развёртки по умолчанию, единицы сцены. */
const DEFAULT_TOLERANCE = 1;

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

  /**
   * Разворачивает открытую кривую в сэмплы с касательными.
   *
   * Разбиение адаптивное: сегмент дробится, пока отклонение управления от
   * хорды больше допуска. Поэтому гладкая кривая получает столько точек,
   * сколько нужно для допуска, а не фиксированное число.
   */
  flattenSamples(points: ScenePoint[], handles: SceneHandle[], tolerance = DEFAULT_TOLERANCE): SceneCurveSample[] {
    if (points.length < 2) {
      return points.map((point, index) => ({
        point: [point[0], point[1]],
        tangent: [1, 0],
        t: 0,
        segment: index,
      }));
    }

    const source = this.normalizeHandles(points, handles);
    const samples: SceneCurveSample[] = [];
    for (let segment = 0; segment < points.length - 1; segment += 1) {
      const start = points[segment];
      const end = points[segment + 1];
      const controlOne: ScenePoint = [start[0] + source[segment].out[0], start[1] + source[segment].out[1]];
      const controlTwo: ScenePoint = [end[0] + source[segment + 1].in[0], end[1] + source[segment + 1].in[1]];
      const raw: { point: ScenePoint; t: number }[] = [{ point: [start[0], start[1]], t: 0 }];
      this.subdivide(start, controlOne, controlTwo, end, 0, 1, 0, tolerance, raw);
      for (const item of raw) {
        const sample: SceneCurveSample = {
          point: item.point,
          tangent: this.cubicTangent(start, controlOne, controlTwo, end, item.t),
          t: item.t,
          segment,
        };
        const previous = samples[samples.length - 1];
        if (previous !== undefined && this.sameSample(previous, sample)) {
          continue;
        }

        samples.push(sample);
      }
    }

    return samples;
  }

  /** Разворачивает открытую кривую в полилинию. */
  flatten(points: ScenePoint[], handles: SceneHandle[], tolerance = DEFAULT_TOLERANCE): ScenePoint[] {
    return this.flattenSamples(points, handles, tolerance).map((sample) => sample.point);
  }

  /**
   * Разворачивает замкнутый контур в полилинию.
   *
   * Сегмент `i → i+1` использует `out` вершины `i` и `in` вершины `i+1`;
   * последний сегмент замыкается на первую вершину.
   */
  flattenContour(points: ScenePoint[], handles: SceneHandle[], tolerance = DEFAULT_TOLERANCE): ScenePoint[] {
    if (points.length < 3) {
      return points.map((point): ScenePoint => [point[0], point[1]]);
    }

    if (!this.isCurved(handles)) {
      return points.map((point): ScenePoint => [point[0], point[1]]);
    }

    const source = this.normalizeHandles(points, handles);
    const flat: ScenePoint[] = [[points[0][0], points[0][1]]];
    for (let index = 0; index < points.length; index += 1) {
      const next = (index + 1) % points.length;
      const start = points[index];
      const end = points[next];
      const controlOne: ScenePoint = [start[0] + source[index].out[0], start[1] + source[index].out[1]];
      const controlTwo: ScenePoint = [end[0] + source[next].in[0], end[1] + source[next].in[1]];
      const raw: { point: ScenePoint; t: number }[] = [];
      this.subdivide(start, controlOne, controlTwo, end, 0, 1, 0, tolerance, raw);
      for (const item of raw) {
        flat.push(item.point);
      }
    }

    // Последний сегмент возвращается в первую вершину: дубликат не нужен.
    const last = flat[flat.length - 1];
    if (flat.length > 1 && last !== undefined && this.distance(flat[0], last) < 1e-6) {
      flat.pop();
    }

    return flat;
  }

  /** Единичная касательная кубической кривой Безье. */
  cubicTangent(
    start: ScenePoint,
    controlOne: ScenePoint,
    controlTwo: ScenePoint,
    end: ScenePoint,
    t: number,
  ): ScenePoint {
    const inverse = 1 - t;
    const a = 3 * inverse * inverse;
    const b = 6 * inverse * t;
    const c = 3 * t * t;
    const x = a * (controlOne[0] - start[0]) + b * (controlTwo[0] - controlOne[0]) + c * (end[0] - controlTwo[0]);
    const y = a * (controlOne[1] - start[1]) + b * (controlTwo[1] - controlOne[1]) + c * (end[1] - controlTwo[1]);
    const length = Math.hypot(x, y);
    if (length < 1e-9) {
      return this.directionOrUnit([end[0] - start[0], end[1] - start[1]]);
    }

    return [x / length, y / length];
  }

  /** Допуск развёртки в единицах сцены под допуск на экране, пиксели. */
  toleranceFor(zoom: number, pixels = 0.4): number {
    if (!Number.isFinite(zoom) || zoom <= 0) {
      return DEFAULT_TOLERANCE;
    }

    return Math.min(8, Math.max(0.02, pixels / zoom));
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

  /** Единичный вектор с запасным направлением для вырожденного случая. */
  private directionOrUnit(vector: ScenePoint): ScenePoint {
    const length = Math.hypot(vector[0], vector[1]);
    if (length < 1e-9) {
      return [1, 0];
    }

    return [vector[0] / length, vector[1] / length];
  }

  /**
   * Рекурсивно дробит кубический сегмент, пока он не станет плоским.
   *
   * В `out` попадает только правый конец каждого полученного куска.
   */
  private subdivide(
    start: ScenePoint,
    controlOne: ScenePoint,
    controlTwo: ScenePoint,
    end: ScenePoint,
    from: number,
    to: number,
    depth: number,
    tolerance: number,
    out: { point: ScenePoint; t: number }[],
  ): void {
    if (depth >= MAX_SUBDIVISION_DEPTH || this.deviation(start, controlOne, controlTwo, end) <= tolerance) {
      out.push({ point: [end[0], end[1]], t: to });

      return;
    }

    const parts = this.splitCubic(start, controlOne, controlTwo, end, 0.5);
    const middle = (from + to) / 2;
    this.subdivide(parts.left[0], parts.left[1], parts.left[2], parts.left[3], from, middle, depth + 1, tolerance, out);
    this.subdivide(
      parts.right[0],
      parts.right[1],
      parts.right[2],
      parts.right[3],
      middle,
      to,
      depth + 1,
      tolerance,
      out,
    );
  }

  /** Насколько управляющие точки уводят сегмент от хорды. */
  private deviation(start: ScenePoint, controlOne: ScenePoint, controlTwo: ScenePoint, end: ScenePoint): number {
    const dx = end[0] - start[0];
    const dy = end[1] - start[1];
    const length = Math.hypot(dx, dy);
    if (length < 1e-9) {
      return Math.max(this.distance(start, controlOne), this.distance(start, controlTwo));
    }

    const first = Math.abs((controlOne[0] - start[0]) * dy - (controlOne[1] - start[1]) * dx) / length;
    const second = Math.abs((controlTwo[0] - start[0]) * dy - (controlTwo[1] - start[1]) * dx) / length;

    return Math.max(first, second);
  }

  /** Один и тот же сэмпл: совпадает точка и почти совпадает касательная. */
  private sameSample(left: SceneCurveSample, right: SceneCurveSample): boolean {
    if (this.distance(left.point, right.point) > 1e-6) {
      return false;
    }

    return left.tangent[0] * right.tangent[0] + left.tangent[1] * right.tangent[1] > 0.9995;
  }

  /** Линейная интерполяция точек. */
  private lerp(from: ScenePoint, to: ScenePoint, t: number): ScenePoint {
    return [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t];
  }
}
