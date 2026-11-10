import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';

/**
 * Геометрия элементов поля: границы, попадание и расстояние до контура.
 */
export class SceneGeometryService {
  /** Границы фигуры в единицах сцены. */
  bounds(shape: SceneShape): SceneBounds {
    if (shape.type === 'rect') {
      return { x: shape.x, y: shape.y, width: shape.w, height: shape.h };
    }

    if (shape.type === 'circle') {
      return { x: shape.cx - shape.r, y: shape.cy - shape.r, width: shape.r * 2, height: shape.r * 2 };
    }

    if (shape.type === 'point') {
      return { x: shape.x, y: shape.y, width: 0, height: 0 };
    }

    const points = shape.points;
    const xs = points.map((point) => point[0]);
    const ys = points.map((point) => point[1]);
    const extra = shape.type === 'wall' ? shape.width / 2 : 0;

    return {
      x: Math.min(...xs) - extra,
      y: Math.min(...ys) - extra,
      width: Math.max(...xs) - Math.min(...xs) + extra * 2,
      height: Math.max(...ys) - Math.min(...ys) + extra * 2,
    };
  }

  /** Точки контура фигуры. */
  points(shape: SceneShape): ScenePoint[] {
    if (shape.type === 'point') {
      return [[shape.x, shape.y]];
    }

    if (shape.type === 'rect') {
      return [
        [shape.x, shape.y],
        [shape.x + shape.w, shape.y],
        [shape.x + shape.w, shape.y + shape.h],
        [shape.x, shape.y + shape.h],
      ];
    }

    if (shape.type === 'circle') {
      return [];
    }

    return shape.points;
  }

  /** Попадает ли точка в фигуру с допуском. */
  contains(shape: SceneShape, point: ScenePoint, tolerance: number): boolean {
    if (shape.type === 'point') {
      return this.distance(point, [shape.x, shape.y]) <= tolerance;
    }

    if (shape.type === 'circle') {
      return this.distance(point, [shape.cx, shape.cy]) <= shape.r + tolerance;
    }

    if (shape.type === 'rect') {
      return (
        point[0] >= shape.x - tolerance &&
        point[0] <= shape.x + shape.w + tolerance &&
        point[1] >= shape.y - tolerance &&
        point[1] <= shape.y + shape.h + tolerance
      );
    }

    if (shape.type === 'wall') {
      return this.distanceToPolyline(point, shape.points) <= shape.width / 2 + tolerance;
    }

    return this.pointInPolygon(point, shape.points) || this.distanceToPolyline(point, shape.points) <= tolerance;
  }

  /** Центр фигуры. */
  center(shape: SceneShape): ScenePoint {
    const bounds = this.bounds(shape);

    return [bounds.x + bounds.width / 2, bounds.y + bounds.height / 2];
  }

  /** Расстояние между точками. */
  distance(from: ScenePoint, to: ScenePoint): number {
    return Math.hypot(to[0] - from[0], to[1] - from[1]);
  }

  /** Минимальное расстояние до полилинии. */
  distanceToPolyline(point: ScenePoint, points: ScenePoint[]): number {
    let result = Number.POSITIVE_INFINITY;
    for (let index = 0; index < points.length - 1; index += 1) {
      result = Math.min(result, this.distanceToSegment(point, points[index], points[index + 1]));
    }

    return result;
  }

  /** Расстояние до отрезка. */
  distanceToSegment(point: ScenePoint, start: ScenePoint, end: ScenePoint): number {
    const dx = end[0] - start[0];
    const dy = end[1] - start[1];
    const lengthSquared = dx * dx + dy * dy;
    if (lengthSquared === 0) {
      return this.distance(point, start);
    }

    const t = Math.max(0, Math.min(1, ((point[0] - start[0]) * dx + (point[1] - start[1]) * dy) / lengthSquared));

    return this.distance(point, [start[0] + t * dx, start[1] + t * dy]);
  }

  /** Точка внутри многоугольника. */
  pointInPolygon(point: ScenePoint, polygon: ScenePoint[]): boolean {
    let inside = false;
    for (let current = 0, previous = polygon.length - 1; current < polygon.length; previous = current++) {
      const currentPoint = polygon[current];
      const previousPoint = polygon[previous];
      const crosses =
        currentPoint[1] > point[1] !== previousPoint[1] > point[1] &&
        point[0] <
          ((previousPoint[0] - currentPoint[0]) * (point[1] - currentPoint[1])) / (previousPoint[1] - currentPoint[1]) +
            currentPoint[0];
      if (crosses) {
        inside = !inside;
      }
    }

    return inside;
  }
}
