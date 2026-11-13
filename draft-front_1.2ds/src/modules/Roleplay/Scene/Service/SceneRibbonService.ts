import type { SceneHandle } from '@/modules/Roleplay/Scene/Dto/SceneHandle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneCurveService } from '@/modules/Roleplay/Scene/Service/SceneCurveService';

/** Геометрия ленты: осевая линия, ширины, оба края и длины вдоль осевой. */
interface RibbonGeometry {
  center: ScenePoint[];
  widths: number[];
  lengths: number[];
  left: ScenePoint[];
  right: ScenePoint[];
}

/** Предел растяжения митры на острых углах: иначе край даёт иглы и петли. */
const MITER_LIMIT = 2.5;

/**
 * Геометрия ленты: открытая кривая с шириной по вершинам.
 *
 * Река и дорога — лента; край строится по аналитической нормали к осевой.
 */
export class SceneRibbonService {
  constructor(private readonly curves: SceneCurveService) {}

  /**
   * Лента по вершинам, ручкам и ширинам.
   *
   * `pad` расширяет её поперёк, `startPad`/`endPad` — вдоль осевой за концы.
   * Продление нужно открытым концам: река упирается в край поля, и торец
   * должен уйти под обрезку, а не читаться берегом.
   */
  geometry(
    points: ScenePoint[],
    handles: SceneHandle[],
    widths: number[],
    tolerance = 1,
    pad = 0,
    startPad = 0,
    endPad = 0,
  ): RibbonGeometry {
    const samples = this.curves.flattenSamples(points, handles, tolerance);
    // У полилинии без ручек на изломе остаются две точки с одной позицией и
    // разными касательными. Сводим их в одну вершину, иначе лента получает
    // нулевой по длине веер и внутренний край складывается в петлю.
    const center: ScenePoint[] = [];
    const flatWidths: number[] = [];
    for (const sample of samples) {
      const width = Math.max(1, this.widthAt(widths, sample.segment, sample.t));
      const previous = center[center.length - 1];
      if (previous !== undefined && this.curves.distance(previous, sample.point) <= 1e-6) {
        flatWidths[flatWidths.length - 1] = Math.max(flatWidths[flatWidths.length - 1] ?? width, width);
        continue;
      }

      center.push(sample.point);
      flatWidths.push(width);
    }

    const lengths: number[] = [];
    let length = 0;
    for (let index = 0; index < center.length; index += 1) {
      if (index > 0) {
        length += this.curves.distance(center[index - 1], center[index]);
      }
      lengths.push(length);
    }

    const left: ScenePoint[] = [];
    const right: ScenePoint[] = [];
    for (let index = 0; index < center.length; index += 1) {
      const half = flatWidths[index] / 2 + pad;
      const normal = this.edgeNormal(center, index);
      left.push([center[index][0] + normal[0] * half, center[index][1] + normal[1] * half]);
      right.push([center[index][0] - normal[0] * half, center[index][1] - normal[1] * half]);
    }

    if (samples.length > 0) {
      this.extendEnd(samples[0], center, flatWidths, lengths, left, right, pad, -startPad, true);
      this.extendEnd(samples[samples.length - 1], center, flatWidths, lengths, left, right, pad, endPad, false);
    }

    return { center, widths: flatWidths, lengths, left, right };
  }

  /** Замкнутый контур ленты для заливки. */
  outline(
    points: ScenePoint[],
    handles: SceneHandle[],
    widths: number[],
    tolerance = 1,
    pad = 0,
    startPad = 0,
    endPad = 0,
  ): ScenePoint[] {
    const geometry = this.geometry(points, handles, widths, tolerance, pad, startPad, endPad);

    return [...geometry.left, ...[...geometry.right].reverse()];
  }

  /**
   * Нормаль края в вершине.
   *
   * На изломе берётся митра соседних сегментов с пределом растяжения: без
   * него внутренний край складывается в петлю, а внешний уходит иглой.
   */
  private edgeNormal(center: readonly ScenePoint[], index: number): ScenePoint {
    const last = center.length - 1;
    if (last < 1) {
      return [0, 1];
    }

    if (index === 0) {
      return this.segmentNormal(center[0], center[1]);
    }

    if (index === last) {
      return this.segmentNormal(center[last - 1], center[last]);
    }

    const before = this.segmentNormal(center[index - 1], center[index]);
    const after = this.segmentNormal(center[index], center[index + 1]);
    const sumX = before[0] + after[0];
    const sumY = before[1] + after[1];
    const sum = Math.hypot(sumX, sumY);
    if (sum < 1e-6) {
      return after;
    }

    const miter: ScenePoint = [sumX / sum, sumY / sum];
    const dot = miter[0] * after[0] + miter[1] * after[1];
    const scale = dot > 1 / MITER_LIMIT ? Math.min(MITER_LIMIT, 1 / dot) : 1;

    return [miter[0] * scale, miter[1] * scale];
  }

  /** Единичная нормаль сегмента влево от направления движения. */
  private segmentNormal(start: ScenePoint, end: ScenePoint): ScenePoint {
    const dx = end[0] - start[0];
    const dy = end[1] - start[1];
    const length = Math.hypot(dx, dy);
    if (length < 1e-9) {
      return [0, 1];
    }

    return [-dy / length, dx / length];
  }

  /** Продлевает ленту за конец: сдвиг по касательной плюс та же нормаль. */
  private extendEnd(
    sample: { point: ScenePoint; tangent: ScenePoint },
    center: ScenePoint[],
    widths: number[],
    lengths: number[],
    left: ScenePoint[],
    right: ScenePoint[],
    pad: number,
    shift: number,
    atStart: boolean,
  ): void {
    if (shift === 0) {
      return;
    }

    const width = atStart ? widths[0] : widths[widths.length - 1];
    const half = width / 2 + pad;
    const normal: ScenePoint = [-sample.tangent[1], sample.tangent[0]];
    const point: ScenePoint = [
      sample.point[0] + sample.tangent[0] * shift,
      sample.point[1] + sample.tangent[1] * shift,
    ];
    const length = (atStart ? lengths[0] : lengths[lengths.length - 1]) + shift;
    const leftPoint: ScenePoint = [point[0] + normal[0] * half, point[1] + normal[1] * half];
    const rightPoint: ScenePoint = [point[0] - normal[0] * half, point[1] - normal[1] * half];
    if (atStart) {
      center.unshift(point);
      widths.unshift(width);
      lengths.unshift(length);
      left.unshift(leftPoint);
      right.unshift(rightPoint);

      return;
    }

    center.push(point);
    widths.push(width);
    lengths.push(length);
    left.push(leftPoint);
    right.push(rightPoint);
  }

  /** Расстояние от точки до ленты за вычетом половины ширины. */
  distance(point: ScenePoint, points: ScenePoint[], handles: SceneHandle[], widths: number[], tolerance = 1): number {
    const geometry = this.geometry(points, handles, widths, tolerance);
    let nearest = Number.POSITIVE_INFINITY;
    for (let index = 1; index < geometry.center.length; index += 1) {
      const half = Math.max(geometry.widths[index - 1], geometry.widths[index]) / 2;
      const segment = this.distanceToSegment(point, geometry.center[index - 1], geometry.center[index]);

      nearest = Math.min(nearest, Math.max(0, segment - half));
    }

    return nearest;
  }

  /** Положение ручки ширины у вершины: по нормали на половину ширины плюс отступ. */
  widthHandlePoint(
    points: ScenePoint[],
    handles: SceneHandle[],
    widths: number[],
    index: number,
    offset: number,
  ): ScenePoint {
    const point = points[index];
    const pathHandles = this.curves.normalizeHandles(points, handles);
    const direction = this.curves.tangent(points, pathHandles, index);
    const normal: ScenePoint = [-direction[1], direction[0]];
    const distance = Math.max(1, widths[index] ?? 1) / 2 + offset;

    return [point[0] + normal[0] * distance, point[1] + normal[1] * distance];
  }

  /** Ширина на сегменте с параметром `t`: линейно между вершинами. */
  private widthAt(widths: number[], segment: number, t: number): number {
    const start = Math.max(1, widths[segment] ?? widths[0] ?? 1);
    const end = Math.max(1, widths[segment + 1] ?? start);

    return start + (end - start) * t;
  }

  /** Расстояние до отрезка. */
  private distanceToSegment(point: ScenePoint, start: ScenePoint, end: ScenePoint): number {
    const dx = end[0] - start[0];
    const dy = end[1] - start[1];
    const lengthSquared = dx * dx + dy * dy;
    if (lengthSquared === 0) {
      return this.curves.distance(point, start);
    }

    const t = Math.max(0, Math.min(1, ((point[0] - start[0]) * dx + (point[1] - start[1]) * dy) / lengthSquared));

    return this.curves.distance(point, [start[0] + t * dx, start[1] + t * dy]);
  }
}
