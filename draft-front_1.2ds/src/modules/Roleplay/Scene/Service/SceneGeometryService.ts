import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SceneCurveService } from '@/modules/Roleplay/Scene/Service/SceneCurveService';
import { SceneRibbonService } from '@/modules/Roleplay/Scene/Service/SceneRibbonService';

/** Насколько удлинять открытый конец ленты, ипари: торец уходит за край поля. */
const OPEN_END_PAST_IPARI = 1;

/** Дальше какого расстояния до края поля конец реки считается закрытым, ипари. */
const OPEN_END_REACH_IPARI = 10;

/** Подготовленная фигура: попадание и границы без повторной тесселяции контура. */
export interface SceneShapeProbe {
  bounds: SceneBounds;
  contains(point: ScenePoint, tolerance: number): boolean;
}

/**
 * Геометрия элементов поля: границы, попадание и расстояние до контура.
 */
export class SceneGeometryService {
  private readonly curves: SceneCurveService;

  private readonly ribbons: SceneRibbonService;

  constructor(curves: SceneCurveService = new SceneCurveService()) {
    this.curves = curves;
    this.ribbons = new SceneRibbonService(curves);
  }

  /** Границы фигуры в единицах сцены; `rotation` — поворот прямоугольника. */
  bounds(shape: SceneShape, rotation = 0): SceneBounds {
    if (shape.type === 'rect') {
      if (this.normalizeRotation(rotation) === 0) {
        return { x: shape.x, y: shape.y, width: shape.w, height: shape.h };
      }

      return this.boundsOfPoints(this.rectCorners(shape, rotation));
    }

    if (shape.type === 'circle') {
      return { x: shape.cx - shape.r, y: shape.cy - shape.r, width: shape.r * 2, height: shape.r * 2 };
    }

    if (shape.type === 'point') {
      return { x: shape.x, y: shape.y, width: 0, height: 0 };
    }

    if (shape.type === 'path') {
      const geometry = this.ribbons.geometry(shape.points, shape.handles ?? [], shape.widths);

      return this.boundsOfPoints([...geometry.left, ...geometry.right]);
    }

    const points =
      shape.type === 'polygon' ? this.curves.flattenContour(shape.points, shape.handles ?? []) : shape.points;
    const bounds = this.boundsOfPoints(points);
    const extra = shape.type === 'wall' ? shape.width / 2 : 0;

    return {
      x: bounds.x - extra,
      y: bounds.y - extra,
      width: bounds.width + extra * 2,
      height: bounds.height + extra * 2,
    };
  }

  /**
   * Продление открытых концов ленты в единицах сцены.
   *
   * Конец реки, стоящий у края поля, — не берег: его надо довести до края,
   * чтобы торец ушёл под обрезку поля. Конец в глубине поля (река впадает в
   * озеро) остаётся закрытым: там берег настоящий.
   */
  openEndPads(shape: SceneShape, board: SceneBounds | null, resolution: number): [number, number] {
    if (shape.type !== 'path' || board === null) {
      return [0, 0];
    }

    const geometry = this.ribbons.geometry(shape.points, shape.handles ?? [], shape.widths);
    const count = geometry.center.length;
    if (count < 2) {
      return [0, 0];
    }

    const base = Math.max(1, resolution);
    const reach = base * OPEN_END_REACH_IPARI;
    const past = base * OPEN_END_PAST_IPARI;
    const padAt = (point: ScenePoint, direction: ScenePoint): number => {
      const inside =
        point[0] > board.x &&
        point[0] < board.x + board.width &&
        point[1] > board.y &&
        point[1] < board.y + board.height;
      if (!inside) {
        // Торец уже за полем: хватит небольшого продления под обрезку.
        return past;
      }

      const exit = this.rayExit(point, direction, board);
      if (exit === null || exit > reach) {
        return 0;
      }

      return exit + past;
    };
    const startDirection = this.direction(geometry.center[0], geometry.center[1]);
    const endDirection = this.direction(geometry.center[count - 1], geometry.center[count - 2]);

    return [padAt(geometry.center[0], startDirection), padAt(geometry.center[count - 1], endDirection)];
  }

  /** Направление из `from` в `to`; ноль, если точки совпали. */
  private direction(from: ScenePoint, to: ScenePoint): ScenePoint {
    const dx = from[0] - to[0];
    const dy = from[1] - to[1];
    const length = Math.hypot(dx, dy);
    if (length === 0) {
      return [0, 0];
    }

    return [dx / length, dy / length];
  }

  /** Расстояние до края поля по лучу из точки внутри поля; null — луч не идёт. */
  private rayExit(point: ScenePoint, direction: ScenePoint, board: SceneBounds): number | null {
    if (direction[0] === 0 && direction[1] === 0) {
      return null;
    }

    let exit = Number.POSITIVE_INFINITY;
    if (direction[0] > 1e-6) {
      exit = Math.min(exit, (board.x + board.width - point[0]) / direction[0]);
    }
    if (direction[0] < -1e-6) {
      exit = Math.min(exit, (board.x - point[0]) / direction[0]);
    }
    if (direction[1] > 1e-6) {
      exit = Math.min(exit, (board.y + board.height - point[1]) / direction[1]);
    }
    if (direction[1] < -1e-6) {
      exit = Math.min(exit, (board.y - point[1]) / direction[1]);
    }

    return Number.isFinite(exit) && exit > 0 ? exit : null;
  }

  /** Точки контура фигуры; `rotation` — поворот прямоугольника. */
  points(shape: SceneShape, rotation = 0): ScenePoint[] {
    if (shape.type === 'point') {
      return [[shape.x, shape.y]];
    }

    if (shape.type === 'rect') {
      return this.rectCorners(shape, rotation);
    }

    if (shape.type === 'circle') {
      return [];
    }

    if (shape.type === 'polygon') {
      return this.curves.flattenContour(shape.points, shape.handles ?? []);
    }

    if (shape.type === 'path') {
      return this.ribbons.geometry(shape.points, shape.handles ?? [], shape.widths).center;
    }

    return shape.points;
  }

  /** Попадает ли точка в фигуру с допуском; `rotation` — поворот прямоугольника. */
  contains(shape: SceneShape, point: ScenePoint, tolerance: number, rotation = 0): boolean {
    if (shape.type === 'point') {
      return this.distance(point, [shape.x, shape.y]) <= tolerance;
    }

    if (shape.type === 'circle') {
      return this.distance(point, [shape.cx, shape.cy]) <= shape.r + tolerance;
    }

    if (shape.type === 'rect') {
      const local =
        this.normalizeRotation(rotation) === 0 ? point : this.rotatePoint(point, this.rectCenter(shape), -rotation);

      return (
        local[0] >= shape.x - tolerance &&
        local[0] <= shape.x + shape.w + tolerance &&
        local[1] >= shape.y - tolerance &&
        local[1] <= shape.y + shape.h + tolerance
      );
    }

    if (shape.type === 'wall') {
      return this.distanceToPolyline(point, shape.points) <= shape.width / 2 + tolerance;
    }

    if (shape.type === 'path') {
      return this.ribbons.distance(point, shape.points, shape.handles ?? [], shape.widths) <= tolerance;
    }

    const outline = this.curves.flattenContour(shape.points, shape.handles ?? []);

    return this.pointInPolygon(point, outline) || this.distanceToPolyline(point, outline) <= tolerance;
  }

  /**
   * Готовит фигуру для многократных проверок попадания.
   *
   * Контур полигона и осевая ленты тесселируются один раз: `contains` для
   * каждой точки делает это заново, и на десятках тысяч проверок раскладка
   * травы упирается в кривые. Возвращённая проверка даёт тот же результат.
   */
  probe(shape: SceneShape, rotation = 0): SceneShapeProbe {
    if (shape.type === 'rect' && this.normalizeRotation(rotation) !== 0) {
      return {
        bounds: this.bounds(shape, rotation),
        contains: (point, tolerance) => this.contains(shape, point, tolerance, rotation),
      };
    }

    if (shape.type === 'polygon') {
      const outline = this.curves.flattenContour(shape.points, shape.handles ?? []);

      return {
        bounds: this.boundsOfPoints(outline),
        contains: (point, tolerance) =>
          this.pointInPolygon(point, outline) || this.distanceToPolyline(point, outline) <= tolerance,
      };
    }

    if (shape.type === 'path') {
      const ribbon = this.ribbons.geometry(shape.points, shape.handles ?? [], shape.widths);

      return {
        bounds: this.boundsOfPoints([...ribbon.left, ...ribbon.right]),
        contains: (point, tolerance) => this.distanceToRibbon(point, ribbon) <= tolerance,
      };
    }

    return {
      bounds: this.bounds(shape),
      contains: (point, tolerance) => this.contains(shape, point, tolerance),
    };
  }

  /** Расстояние до уже тесселированной ленты. */
  private distanceToRibbon(point: ScenePoint, ribbon: { center: ScenePoint[]; widths: number[] }): number {
    let nearest = Number.POSITIVE_INFINITY;
    for (let index = 1; index < ribbon.center.length; index += 1) {
      const half = Math.max(ribbon.widths[index - 1], ribbon.widths[index]) / 2;
      const segment = this.distanceToSegment(point, ribbon.center[index - 1], ribbon.center[index]);
      nearest = Math.min(nearest, Math.max(0, segment - half));
    }

    return nearest;
  }

  /** Центр фигуры; у прямоугольника поворот не меняет центр. */
  center(shape: SceneShape, rotation = 0): ScenePoint {
    const bounds = this.bounds(shape, rotation);

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

  /**
   * Замкнутый контур фигуры для навигации.
   *
   * Кривые разворачиваются с тем же допуском, что и в рендере. Круг
   * аппроксимируется многоугольником: навигации не нужен аналитический круг,
   * а булевы операции работают с полигонами. Точка контура не имеет.
   */
  outline(shape: SceneShape, pad = 0, circleSegments = 48, rotation = 0): ScenePoint[] {
    if (shape.type === 'point') {
      return [];
    }

    if (shape.type === 'rect') {
      const x = shape.x - pad;
      const y = shape.y - pad;
      const w = shape.w + pad * 2;
      const h = shape.h + pad * 2;
      const corners: ScenePoint[] = [
        [x, y],
        [x + w, y],
        [x + w, y + h],
        [x, y + h],
      ];

      return this.normalizeRotation(rotation) === 0
        ? corners
        : corners.map((point) => this.rotatePoint(point, this.rectCenter(shape), rotation));
    }

    if (shape.type === 'circle') {
      const radius = Math.max(0, shape.r + pad);
      const segments = Math.max(12, Math.min(128, Math.floor(circleSegments)));
      const points: ScenePoint[] = [];
      for (let index = 0; index < segments; index += 1) {
        const angle = (index / segments) * Math.PI * 2;
        points.push([shape.cx + Math.cos(angle) * radius, shape.cy + Math.sin(angle) * radius]);
      }

      return points;
    }

    if (shape.type === 'polygon') {
      return this.curves.flattenContour(shape.points, shape.handles ?? []);
    }

    if (shape.type === 'wall') {
      const widths = shape.points.map(() => shape.width);

      return this.ribbons.outline(shape.points, [], widths, 1, pad);
    }

    return this.ribbons.outline(shape.points, shape.handles ?? [], shape.widths, 1, pad);
  }

  /** Углы прямоугольника с поворотом вокруг центра. */
  private rectCorners(shape: { x: number; y: number; w: number; h: number }, rotation: number): ScenePoint[] {
    const corners: ScenePoint[] = [
      [shape.x, shape.y],
      [shape.x + shape.w, shape.y],
      [shape.x + shape.w, shape.y + shape.h],
      [shape.x, shape.y + shape.h],
    ];
    if (this.normalizeRotation(rotation) === 0) {
      return corners;
    }

    const center = this.rectCenter(shape);

    return corners.map((point) => this.rotatePoint(point, center, rotation));
  }

  /** Центр прямоугольника. */
  private rectCenter(shape: { x: number; y: number; w: number; h: number }): ScenePoint {
    return [shape.x + shape.w / 2, shape.y + shape.h / 2];
  }

  /** Поворот точки вокруг центра на угол в градусах. */
  private rotatePoint(point: ScenePoint, center: ScenePoint, degrees: number): ScenePoint {
    const radians = (degrees * Math.PI) / 180;
    const cosine = Math.cos(radians);
    const sine = Math.sin(radians);
    const dx = point[0] - center[0];
    const dy = point[1] - center[1];

    return [center[0] + dx * cosine - dy * sine, center[1] + dx * sine + dy * cosine];
  }

  /** Угол в диапазон 0–360. */
  private normalizeRotation(rotation: number): number {
    return ((rotation % 360) + 360) % 360;
  }

  /** Границы набора точек. */
  private boundsOfPoints(points: ScenePoint[]): SceneBounds {
    if (points.length === 0) {
      return { x: 0, y: 0, width: 0, height: 0 };
    }

    const xs = points.map((point) => point[0]);
    const ys = points.map((point) => point[1]);

    return {
      x: Math.min(...xs),
      y: Math.min(...ys),
      width: Math.max(...xs) - Math.min(...xs),
      height: Math.max(...ys) - Math.min(...ys),
    };
  }
}
