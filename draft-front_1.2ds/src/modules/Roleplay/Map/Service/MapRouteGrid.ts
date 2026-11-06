import type { MapAreaShape } from '@/modules/Roleplay/Map/Dto/MapAreaShape';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapLineHandle } from '@/modules/Roleplay/Map/Dto/MapLineHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import type { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';

const TARGET_CELLS = 150000;
const DEFAULT_SPEED = 5000;
const MAX_SPEED = 10000;
const COST_FACTOR = 10000;
const NO_ROUTE = 'Между точками нет проходимого маршрута';

interface RouteField {
  width: number;
  height: number;
  cell: number;
  columns: number;
  rows: number;
  speeds: Int16Array;
}

interface Surface {
  id: number;
  path: MapPoint[];
  speed: number;
  sortOrder: number;
}

interface Ribbon {
  id: number;
  line: MapLine;
  segmentSpeeds: number[];
  defaultSpeed: number;
  sortOrder: number;
}

interface RouteObject {
  id: number;
  path: MapPoint[];
  speed: number;
  sortOrder: number;
}

/** Маршрут по проходимой зоне варианта: сетка и A*. */
export class MapRouteGrid {
  constructor(private readonly geometry: MapGeometryService) {}

  build(map: MapDetail, variant: MapVariant): RouteField {
    const surfaces: Surface[] = [];
    const objects: RouteObject[] = [];
    const shapes = new Map<number, MapAreaShape>();
    for (const area of map.areas) {
      const shape = area.shapes.find((candidate) => candidate.variantId === variant.id && candidate.isPresent);
      if (shape) shapes.set(area.id, shape);
    }
    for (const area of map.areas) {
      const shape = shapes.get(area.id);
      if (!shape) continue;
      const path = this.geometry.flattenContour(shape.path, shape.handles ?? []);
      if (path.length < 3) continue;
      if (area.layer === 'surface') {
        surfaces.push({
          id: area.id,
          path,
          speed: area.speed,
          sortOrder: area.sortOrder,
        });
      } else {
        objects.push({
          id: area.id,
          path,
          speed: area.speed,
          sortOrder: area.sortOrder,
        });
      }
    }
    const ribbons: Ribbon[] = [];
    for (const line of map.lines) {
      if (line.variantId !== variant.id || !line.isPresent || line.points.length < 2) continue;
      ribbons.push({
        id: line.id,
        line,
        defaultSpeed: line.speed,
        segmentSpeeds: line.segments.map((segment) => segment.speed),
        sortOrder: line.sortOrder,
      });
    }

    const width = Math.max(1, variant.baseFile?.width ?? 1200);
    const height = Math.max(1, variant.baseFile?.height ?? 900);
    const cell = Math.max(2, Math.min(1000, Math.ceil(Math.sqrt((width * height) / TARGET_CELLS))));
    const columns = Math.max(1, Math.ceil(width / cell));
    const rows = Math.max(1, Math.ceil(height / cell));
    const field: RouteField = {
      width,
      height,
      cell,
      columns,
      rows,
      speeds: new Int16Array(columns * rows).fill(-1),
    };

    surfaces.sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
    ribbons.sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
    objects.sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
    for (const surface of surfaces) this.paintPolygon(field, surface.path, surface.speed);
    for (const ribbon of ribbons) this.paintRibbon(field, ribbon);
    for (const object of objects) this.paintObject(field, object);
    for (let index = 0; index < field.speeds.length; index += 1) {
      if (field.speeds[index] < 0) field.speeds[index] = DEFAULT_SPEED;
    }


    return field;
  }

  findRoute(
    field: RouteField,
    from: MapPoint,
    to: MapPoint,
    millimetersPerPixel: number | null,
  ): MapRoute {
    const startCell = this.cellOf(field, from);
    const goalCell = this.cellOf(field, to);
    const startPassable = this.passable(field, startCell[0], startCell[1]);
    const goalPassable = this.passable(field, goalCell[0], goalCell[1]);
    const start = startPassable ? startCell : this.nearestPassable(field, startCell[0], startCell[1]);
    const goal = goalPassable ? goalCell : this.nearestPassable(field, goalCell[0], goalCell[1]);
    if (!start || !goal) throw new Error(NO_ROUTE);

    const result = this.aStar(field, start, goal);
    if (!result) throw new Error(NO_ROUTE);
    let points: MapPoint[] = result.cells.map(([x, y]) => this.cellCenter(field, x, y));
    if (points.length === 1) {
      points = [
        startPassable ? this.clampPoint(field, from) : points[0],
        goalPassable ? this.clampPoint(field, to) : points[0],
      ];
    } else {
      if (startPassable) points[0] = this.clampPoint(field, from);
      if (goalPassable) points[points.length - 1] = this.clampPoint(field, to);
    }
    const unique = this.dedupe(points);
    const distancePixels = this.length(unique);
    const scale = millimetersPerPixel;

    return {
      points: unique,
      distancePixels,
      costPixels: result.cost,
      distanceMeters: scale === null ? null : Math.round((distancePixels * scale) / 1000),
      costMeters: scale === null ? null : Math.round((result.cost * scale) / 1000),
    };
  }

  private paintPolygon(field: RouteField, polygon: MapPoint[], speed: number): void {
    const bounds = this.cellBounds(field, polygon);
    for (let y = bounds.top; y <= bounds.bottom; y += 1) {
      for (let x = bounds.left; x <= bounds.right; x += 1) {
        if (this.cellTouchesPolygon(field, x, y, polygon)) {
          field.speeds[y * field.columns + x] = Math.max(0, Math.min(MAX_SPEED, speed));
        }
      }
    }
  }

  private paintObject(field: RouteField, object: RouteObject): void {
    const bounds = this.cellBounds(field, object.path);
    for (let y = bounds.top; y <= bounds.bottom; y += 1) {
      for (let x = bounds.left; x <= bounds.right; x += 1) {
        if (!this.cellTouchesPolygon(field, x, y, object.path)) continue;
        field.speeds[y * field.columns + x] = Math.max(0, Math.min(MAX_SPEED, object.speed));
      }
    }
  }

  private paintRibbon(field: RouteField, ribbon: Ribbon): void {
    const { points, widths, handles } = ribbon.line;
    for (let index = 1; index < points.length; index += 1) {
      const segmentPoints = [points[index - 1], points[index]];
      const segmentWidths = [widths[index - 1] ?? 1, widths[index] ?? 1];
      const segmentHandles: MapLineHandle[] = [
        handles[index - 1] ?? { in: [0, 0], out: [0, 0] },
        handles[index] ?? { in: [0, 0], out: [0, 0] },
      ];
      const flat = this.geometry.flattenLine(segmentPoints, segmentHandles, segmentWidths);
      const speed = ribbon.segmentSpeeds[index - 1] ?? ribbon.defaultSpeed;
      this.paintRibbonStroke(field, flat.points, flat.widths, speed);
    }
  }

  private paintRibbonStroke(field: RouteField, points: MapPoint[], widths: number[], speed: number): void {
    for (let index = 1; index < points.length; index += 1) {
      const half = Math.max(field.cell * 0.6, Math.max(widths[index - 1] ?? 1, widths[index] ?? 1) / 2);
      const start = points[index - 1];
      const end = points[index];
      const left = Math.max(0, Math.floor((Math.min(start[0], end[0]) - half) / field.cell));
      const top = Math.max(0, Math.floor((Math.min(start[1], end[1]) - half) / field.cell));
      const right = Math.min(field.columns - 1, Math.ceil((Math.max(start[0], end[0]) + half) / field.cell));
      const bottom = Math.min(field.rows - 1, Math.ceil((Math.max(start[1], end[1]) + half) / field.cell));
      for (let y = top; y <= bottom; y += 1) {
        for (let x = left; x <= right; x += 1) {
          const center = this.cellCenter(field, x, y);
          if (this.geometry.distanceToSegment(center, start, end) <= half) {
            field.speeds[y * field.columns + x] = Math.max(0, Math.min(MAX_SPEED, speed));
          }
        }
      }
    }
  }

  private cellBounds(field: RouteField, polygon: MapPoint[]): {
    left: number;
    top: number;
    right: number;
    bottom: number;
  } {
    const xs = polygon.map((point) => point[0]);
    const ys = polygon.map((point) => point[1]);

    return {
      left: Math.max(0, Math.floor(Math.min(...xs) / field.cell)),
      right: Math.min(field.columns - 1, Math.floor(Math.max(...xs) / field.cell)),
      top: Math.max(0, Math.floor(Math.min(...ys) / field.cell)),
      bottom: Math.min(field.rows - 1, Math.floor(Math.max(...ys) / field.cell)),
    };
  }

  private cellTouchesPolygon(field: RouteField, x: number, y: number, polygon: MapPoint[]): boolean {
    const x0 = x * field.cell;
    const y0 = y * field.cell;
    const x1 = x0 + field.cell;
    const y1 = y0 + field.cell;
    const samples: MapPoint[] = [
      [x0 + field.cell / 2, y0 + field.cell / 2],
      [x0, y0],
      [x1, y0],
      [x1, y1],
      [x0, y1],
    ];
    if (samples.some((point) => this.geometry.pointInPolygon(point, polygon))) return true;

    return polygon.some((point) => point[0] >= x0 && point[0] <= x1 && point[1] >= y0 && point[1] <= y1);
  }

  private aStar(
    field: RouteField,
    start: MapPoint,
    goal: MapPoint,
  ): { cells: MapPoint[]; cost: number } | null {
    const columns = field.columns;
    const startKey = start[1] * columns + start[0];
    const goalKey = goal[1] * columns + goal[0];
    const costs = new Float64Array(field.speeds.length).fill(Number.POSITIVE_INFINITY);
    const previous = new Int32Array(field.speeds.length).fill(-1);
    const closed = new Uint8Array(field.speeds.length);
    const heap = new MinHeap();
    costs[startKey] = 0;
    heap.push(startKey, 0);
    const deltas: MapPoint[] = [
      [-1, 0],
      [1, 0],
      [0, -1],
      [0, 1],
      [-1, -1],
      [1, -1],
      [-1, 1],
      [1, 1],
    ];
    while (!heap.isEmpty()) {
      const key = heap.pop();
      if (key === null || closed[key]) continue;
      closed[key] = 1;
      if (key === goalKey) {
        const cells: MapPoint[] = [];
        let current = goalKey;
        while (current !== startKey) {
          cells.push([current % columns, Math.floor(current / columns)]);
          current = previous[current] ?? startKey;
        }
        cells.push([startKey % columns, Math.floor(startKey / columns)]);

        return { cells: cells.reverse(), cost: costs[goalKey] };
      }
      const x = key % columns;
      const y = Math.floor(key / columns);
      for (const [dx, dy] of deltas) {
        const nx = x + dx;
        const ny = y + dy;
        if (nx < 0 || ny < 0 || nx >= columns || ny >= field.rows) continue;
        const speed = field.speeds[ny * columns + nx];
        if (speed <= 0) continue;
        if (dx !== 0 && dy !== 0) {
          if (!this.passable(field, x + dx, y) || !this.passable(field, x, y + dy)) continue;
        }
        const step = field.cell * (dx !== 0 && dy !== 0 ? Math.SQRT2 : 1);
        const nextKey = ny * columns + nx;
        const tentative = costs[key] + (step * COST_FACTOR) / speed;
        if (tentative >= costs[nextKey]) continue;
        costs[nextKey] = tentative;
        previous[nextKey] = key;
        heap.push(nextKey, tentative + this.heuristic(nx, ny, goal, field.cell));
      }
    }

    return null;
  }

  private heuristic(x: number, y: number, goal: MapPoint, cell: number): number {
    return Math.hypot((goal[0] - x) * cell, (goal[1] - y) * cell);
  }

  private passable(field: RouteField, x: number, y: number): boolean {
    if (x < 0 || y < 0 || x >= field.columns || y >= field.rows) return false;

    return field.speeds[y * field.columns + x] > 0;
  }

  private nearestPassable(field: RouteField, x: number, y: number): MapPoint | null {
    for (let radius = 1; radius <= 8; radius += 1) {
      for (let dy = -radius; dy <= radius; dy += 1) {
        for (let dx = -radius; dx <= radius; dx += 1) {
          if (Math.abs(dx) !== radius && Math.abs(dy) !== radius) continue;
          if (this.passable(field, x + dx, y + dy)) return [x + dx, y + dy];
        }
      }
    }

    return null;
  }

  private cellOf(field: RouteField, point: MapPoint): MapPoint {
    return [
      Math.max(0, Math.min(field.columns - 1, Math.floor(point[0] / field.cell))),
      Math.max(0, Math.min(field.rows - 1, Math.floor(point[1] / field.cell))),
    ];
  }

  private cellCenter(field: RouteField, x: number, y: number): MapPoint {
    return this.clampPoint(field, [(x + 0.5) * field.cell, (y + 0.5) * field.cell]);
  }

  private clampPoint(field: RouteField, point: MapPoint): MapPoint {
    return [
      Math.max(0, Math.min(field.width, Math.round(point[0]))),
      Math.max(0, Math.min(field.height, Math.round(point[1]))),
    ];
  }

  private dedupe(points: MapPoint[]): MapPoint[] {
    const unique: MapPoint[] = [];
    for (const point of points) {
      const last = unique.at(-1);
      if (last && last[0] === point[0] && last[1] === point[1]) continue;
      unique.push(point);
    }

    return unique;
  }

  private length(points: MapPoint[]): number {
    let length = 0;
    for (let index = 1; index < points.length; index += 1) {
      length += this.geometry.distance(points[index - 1], points[index]);
    }

    return length;
  }
}

/** Минимальная двоичная куча по приоритету. */
class MinHeap {
  private keys: number[] = [];

  private priorities: number[] = [];

  isEmpty(): boolean {
    return this.keys.length === 0;
  }

  push(key: number, priority: number): void {
    this.keys.push(key);
    this.priorities.push(priority);
    let index = this.keys.length - 1;
    while (index > 0) {
      const parent = Math.floor((index - 1) / 2);
      if (this.priorities[parent] <= this.priorities[index]) break;
      this.swap(parent, index);
      index = parent;
    }
  }

  pop(): number | null {
    if (this.keys.length === 0) return null;
    const value = this.keys[0];
    const lastKey = this.keys.pop();
    const lastPriority = this.priorities.pop();
    if (lastKey === undefined || lastPriority === undefined) return value;
    if (this.keys.length > 0) {
      this.keys[0] = lastKey;
      this.priorities[0] = lastPriority;
      let index = 0;
      for (;;) {
        const left = index * 2 + 1;
        const right = left + 1;
        let smallest = index;
        if (left < this.keys.length && this.priorities[left] < this.priorities[smallest]) smallest = left;
        if (right < this.keys.length && this.priorities[right] < this.priorities[smallest]) smallest = right;
        if (smallest === index) break;
        this.swap(index, smallest);
        index = smallest;
      }
    }

    return value;
  }

  private swap(left: number, right: number): void {
    [this.keys[left], this.keys[right]] = [this.keys[right], this.keys[left]];
    [this.priorities[left], this.priorities[right]] = [this.priorities[right], this.priorities[left]];
  }
}
