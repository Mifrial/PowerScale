import type { SceneHandleSide } from '@/modules/Roleplay/Scene/Enum/SceneHandleSide';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneCurveService } from '@/modules/Roleplay/Scene/Service/SceneCurveService';
import type { SceneRibbonService } from '@/modules/Roleplay/Scene/Service/SceneRibbonService';

/**
 * Попадание по ручкам и вершинам полигона или ленты.
 *
 * Класс не меняет черновик: он отвечает только на вопрос «что под курсором».
 */
export class SceneVertexService {
  constructor(
    private readonly curves: SceneCurveService,
    private readonly ribbons: SceneRibbonService,
  ) {}

  /** Вершина под точкой. */
  vertexAt(shape: SceneShape, point: ScenePoint, tolerance: number): number | null {
    if (shape.type !== 'polygon' && shape.type !== 'path') {
      return null;
    }

    for (let index = 0; index < shape.points.length; index += 1) {
      if (this.curves.distance(point, shape.points[index]) <= tolerance) {
        return index;
      }
    }

    return null;
  }

  /** Ручка Безье под точкой. */
  handleAt(
    shape: SceneShape,
    point: ScenePoint,
    tolerance: number,
    stub: number,
  ): { index: number; side: SceneHandleSide } | null {
    if (shape.type !== 'polygon' && shape.type !== 'path') {
      return null;
    }

    const handles = this.curves.normalizeHandles(shape.points, shape.handles);
    for (let index = 0; index < shape.points.length; index += 1) {
      const display = this.curves.displayHandles(shape.points, handles, index, stub, shape.type === 'polygon');
      if (this.curves.distance(point, display.in) <= tolerance) {
        return { index, side: 'in' };
      }

      if (this.curves.distance(point, display.out) <= tolerance) {
        return { index, side: 'out' };
      }
    }

    return null;
  }

  /** Ручка ширины ленты под точкой. */
  widthAt(shape: SceneShape, point: ScenePoint, tolerance: number, offset: number): number | null {
    if (shape.type !== 'path') {
      return null;
    }

    for (let index = 0; index < shape.points.length; index += 1) {
      const target = this.ribbons.widthHandlePoint(shape.points, shape.handles ?? [], shape.widths, index, offset);
      if (this.curves.distance(point, target) <= tolerance) {
        return index;
      }
    }

    return null;
  }

  /** Куда вставить вершину: сегмент, параметр `t` и ширина в этой точке. */
  insertion(
    shape: SceneShape,
    point: ScenePoint,
    tolerance: number,
  ): { index: number; t: number; point: ScenePoint; width: number } | null {
    if (shape.type !== 'polygon' && shape.type !== 'path') {
      return null;
    }

    const handles = this.curves.normalizeHandles(shape.points, shape.handles);
    const closed = shape.type === 'polygon';
    const count = closed ? shape.points.length : shape.points.length - 1;
    let best: { index: number; t: number; point: ScenePoint; distance: number } | null = null;
    for (let index = 0; index < count; index += 1) {
      const endIndex = (index + 1) % shape.points.length;
      const start = shape.points[index];
      const end = shape.points[endIndex];
      const controlOne: ScenePoint = [start[0] + handles[index].out[0], start[1] + handles[index].out[1]];
      const controlTwo: ScenePoint = [end[0] + handles[endIndex].in[0], end[1] + handles[endIndex].in[1]];
      const nearest = this.curves.nearestOnCubic(point, start, controlOne, controlTwo, end);
      if (best === null || nearest.distance < best.distance) {
        best = { index, t: nearest.t, point: nearest.point, distance: nearest.distance };
      }
    }

    if (best === null || best.distance > tolerance) {
      return null;
    }

    const width =
      shape.type === 'path'
        ? (shape.widths[best.index] ?? 1) +
          ((shape.widths[(best.index + 1) % shape.widths.length] ?? 1) - (shape.widths[best.index] ?? 1)) * best.t
        : 1;

    return { index: best.index, t: best.t, point: best.point, width };
  }

  /** Делит сегмент в найденной точке, сохраняя форму кривой. */
  split(
    shape: SceneShape,
    insertion: { index: number; t: number; point: ScenePoint; width: number },
  ): {
    index: number;
    point: ScenePoint;
    handle: { in: ScenePoint; out: ScenePoint };
    startHandle: ScenePoint;
    endHandle: ScenePoint;
    width: number;
  } | null {
    if (shape.type !== 'polygon' && shape.type !== 'path') {
      return null;
    }

    const handles = this.curves.normalizeHandles(shape.points, shape.handles);
    const startIndex = insertion.index;
    const endIndex = (startIndex + 1) % shape.points.length;
    const start = shape.points[startIndex];
    const end = shape.points[endIndex];
    const controlOne: ScenePoint = [start[0] + handles[startIndex].out[0], start[1] + handles[startIndex].out[1]];
    const controlTwo: ScenePoint = [end[0] + handles[endIndex].in[0], end[1] + handles[endIndex].in[1]];
    const parts = this.curves.splitCubic(start, controlOne, controlTwo, end, insertion.t);
    const point = parts.left[3];

    return {
      index: startIndex,
      point: [Math.round(point[0]), Math.round(point[1])],
      handle: {
        in: [Math.round(parts.left[2][0] - point[0]), Math.round(parts.left[2][1] - point[1])],
        out: [Math.round(parts.right[1][0] - point[0]), Math.round(parts.right[1][1] - point[1])],
      },
      startHandle: [Math.round(parts.left[1][0] - start[0]), Math.round(parts.left[1][1] - start[1])],
      endHandle: [Math.round(parts.right[2][0] - end[0]), Math.round(parts.right[2][1] - end[1])],
      width: insertion.width,
    };
  }
}
