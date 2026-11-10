import type { SceneHandle } from '@/modules/Roleplay/Scene/Dto/SceneHandle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneCurveService } from '@/modules/Roleplay/Scene/Service/SceneCurveService';

/**
 * Геометрия ленты: открытая кривая с шириной по вершинам.
 *
 * Река и дорога — лента; край строится по нормали к осевой.
 */
export class SceneRibbonService {
  constructor(private readonly curves: SceneCurveService) {}

  /** Лента по вершинам, ручкам и ширинам. */
  geometry(
    points: ScenePoint[],
    handles: SceneHandle[],
    widths: number[],
    samples = 12,
  ): { center: ScenePoint[]; widths: number[]; left: ScenePoint[]; right: ScenePoint[] } {
    const { center, widths: flatWidths } = this.flatten(points, handles, widths, samples);
    const left: ScenePoint[] = [];
    const right: ScenePoint[] = [];
    for (let index = 0; index < center.length; index += 1) {
      const normal = this.normalAt(center, index);
      const half = Math.max(0.5, flatWidths[index] ?? 1) / 2;
      left.push([center[index][0] + normal[0] * half, center[index][1] + normal[1] * half]);
      right.push([center[index][0] - normal[0] * half, center[index][1] - normal[1] * half]);
    }

    return { center, widths: flatWidths, left, right };
  }

  /** Замкнутый контур ленты для заливки. */
  outline(points: ScenePoint[], handles: SceneHandle[], widths: number[], samples = 12): ScenePoint[] {
    const geometry = this.geometry(points, handles, widths, samples);

    return [...geometry.left, ...[...geometry.right].reverse()];
  }

  /** Расстояние от точки до ленты за вычетом половины ширины. */
  distance(point: ScenePoint, points: ScenePoint[], handles: SceneHandle[], widths: number[], samples = 12): number {
    const geometry = this.geometry(points, handles, widths, samples);
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

  /** Разворачивает осевую линию с ширинами в сэмплы. */
  private flatten(
    points: ScenePoint[],
    handles: SceneHandle[],
    widths: number[],
    samples: number,
  ): { center: ScenePoint[]; widths: number[] } {
    if (points.length < 2) {
      return {
        center: points.map((point): ScenePoint => [point[0], point[1]]),
        widths: points.map((_, index) => Math.max(1, widths[index] ?? 1)),
      };
    }

    const pathHandles = this.curves.normalizeHandles(points, handles);
    const steps = Math.max(1, samples);
    const center: ScenePoint[] = [];
    const flatWidths: number[] = [];
    for (let index = 1; index < points.length; index += 1) {
      const start = points[index - 1];
      const end = points[index];
      const controlOne: ScenePoint = [
        start[0] + pathHandles[index - 1].out[0],
        start[1] + pathHandles[index - 1].out[1],
      ];
      const controlTwo: ScenePoint = [end[0] + pathHandles[index].in[0], end[1] + pathHandles[index].in[1]];
      const startWidth = Math.max(1, widths[index - 1] ?? 1);
      const endWidth = Math.max(1, widths[index] ?? startWidth);
      for (let step = index === 1 ? 0 : 1; step <= steps; step += 1) {
        const t = step / steps;
        center.push(this.curves.cubicPoint(start, controlOne, controlTwo, end, t));
        flatWidths.push(startWidth + (endWidth - startWidth) * t);
      }
    }

    return { center, widths: flatWidths };
  }

  /** Единичная нормаль к осевой в сэмпле. */
  private normalAt(points: ScenePoint[], index: number): ScenePoint {
    const previous = points[Math.max(0, index - 1)];
    const next = points[Math.min(points.length - 1, index + 1)];
    const dx = next[0] - previous[0];
    const dy = next[1] - previous[1];
    const length = Math.hypot(dx, dy);
    if (length === 0) {
      return [0, 1];
    }

    return [-dy / length, dx / length];
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
