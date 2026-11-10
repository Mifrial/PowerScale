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

/**
 * Геометрия ленты: открытая кривая с шириной по вершинам.
 *
 * Река и дорога — лента; край строится по аналитической нормали к осевой.
 */
export class SceneRibbonService {
  constructor(private readonly curves: SceneCurveService) {}

  /** Лента по вершинам, ручкам и ширинам; `pad` расширяет её с двух сторон. */
  geometry(points: ScenePoint[], handles: SceneHandle[], widths: number[], tolerance = 1, pad = 0): RibbonGeometry {
    const samples = this.curves.flattenSamples(points, handles, tolerance);
    const center: ScenePoint[] = [];
    const flatWidths: number[] = [];
    const lengths: number[] = [];
    const left: ScenePoint[] = [];
    const right: ScenePoint[] = [];
    let length = 0;
    for (const sample of samples) {
      const width = Math.max(1, this.widthAt(widths, sample.segment, sample.t));
      const half = width / 2 + pad;
      const normal: ScenePoint = [-sample.tangent[1], sample.tangent[0]];
      if (center.length > 0) {
        const previous = center[center.length - 1];
        length += Math.hypot(sample.point[0] - previous[0], sample.point[1] - previous[1]);
      }

      center.push(sample.point);
      flatWidths.push(width);
      lengths.push(length);
      left.push([sample.point[0] + normal[0] * half, sample.point[1] + normal[1] * half]);
      right.push([sample.point[0] - normal[0] * half, sample.point[1] - normal[1] * half]);
    }

    return { center, widths: flatWidths, lengths, left, right };
  }

  /** Замкнутый контур ленты для заливки. */
  outline(points: ScenePoint[], handles: SceneHandle[], widths: number[], tolerance = 1, pad = 0): ScenePoint[] {
    const geometry = this.geometry(points, handles, widths, tolerance, pad);

    return [...geometry.left, ...[...geometry.right].reverse()];
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
