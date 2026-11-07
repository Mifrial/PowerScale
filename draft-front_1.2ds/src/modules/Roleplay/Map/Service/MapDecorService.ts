import type { MapAreaDecor, MapDecorSymbol } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';
import { MAP_DECOR_LIMITS, MAP_DECOR_SETS } from '@/modules/Roleplay/Map/Constant/Map/MAP_DECOR';

/** Точка рассыпки: место, символ и множители формы. */
export interface MapDecorPoint {
  x: number;
  y: number;
  /** Какой символ рисуется в точке. */
  kind: MapDecorSymbol;
  /** Поворот, радианы. */
  angle: number;
  /** Множитель размера, 0.75–1.3. */
  size: number;
  /** Номер варианта формы, 0–3. */
  variant: number;
}

/** Верхняя граница символов на одну область: защита панорамы от гигантских полигонов. */
export const MAP_DECOR_MAX_POINTS = 3000;

/** Предел ячеек выборки: тонкий полигон не должен раздувать проход. */
const MAX_CELLS = 20000;

/** Детерминированная раскладка символов внутри полигона области. */
export class MapDecorService {
  private readonly cache = new Map<string, MapDecorPoint[]>();

  constructor(private readonly geometry: MapGeometryService) {}

  /** Точки рассыпки; повторный вызов с теми же данными берётся из кэша. */
  layout(areaId: number, path: MapPoint[], decor: MapAreaDecor): MapDecorPoint[] {
    const set = decor.set;
    if (!set || path.length < 3) return [];

    const info = MAP_DECOR_SETS[set];
    const density = clamp(decor.density ?? info.density, MAP_DECOR_LIMITS.density);
    if (density <= 0) return [];

    const jitter = clamp(decor.jitter ?? info.jitter, MAP_DECOR_LIMITS.jitter);
    const scale = clamp(decor.scale ?? info.scale, MAP_DECOR_LIMITS.scale);
    const seed = clamp(decor.seed ?? 0, MAP_DECOR_LIMITS.seed) || areaId || 1;
    const key = [areaId, set, density, jitter, scale, seed, pathHash(path)].join(':');

    const cached = this.cache.get(key);
    if (cached) {
      this.cache.delete(key);
      this.cache.set(key, cached);

      return cached;
    }

    const points = this.scatter(path, info.layers, density, jitter, seed);
    this.cache.set(key, points);
    if (this.cache.size > 128) {
      const oldest = this.cache.keys().next().value;
      if (oldest !== undefined) this.cache.delete(oldest);
    }

    return points;
  }

  /** Сбрасывает кэш раскладки. */
  clear(): void {
    this.cache.clear();
  }

  private scatter(
    path: MapPoint[],
    layers: { symbol: MapDecorSymbol; share: number }[],
    density: number,
    jitter: number,
    seed: number,
  ): MapDecorPoint[] {
    const area = this.geometry.polygonArea(path);
    const count = Math.min(MAP_DECOR_MAX_POINTS, Math.round((area / 10000) * density));
    if (count <= 0 || layers.length === 0) return [];

    const [minX, minY, maxX, maxY] = bounds(path);
    let cell = Math.sqrt(area / count);
    if (cell <= 0) return [];

    const spanX = Math.max(cell, maxX - minX);
    const spanY = Math.max(cell, maxY - minY);
    if ((spanX / cell) * (spanY / cell) > MAX_CELLS) {
      cell = Math.sqrt((spanX * spanY) / MAX_CELLS);
    }

    const columns = Math.max(1, Math.ceil(spanX / cell));
    const rows = Math.max(1, Math.ceil(spanY / cell));
    const spread = (jitter / 100) * cell * 0.5;
    const random = seededRandom(seed);
    const points: MapDecorPoint[] = [];

    // Веса символов внутри набора: накопленная доля выбирает символ точки.
    const shares = layers.map((layer) => Math.max(0, layer.share));
    const total = shares.reduce((sum, share) => sum + share, 0) || 1;

    for (let row = 0; row < rows; row += 1) {
      for (let column = 0; column < columns; column += 1) {
        const x = minX + (column + 0.5) * cell + (random() - 0.5) * 2 * spread;
        const y = minY + (row + 0.5) * cell + (random() - 0.5) * 2 * spread;
        const angle = (random() - 0.5) * 0.6;
        const size = 0.75 + random() * 0.55;
        const variant = Math.floor(random() * 4);
        const pick = random() * total;
        const point: MapPoint = [x, y];
        if (!this.geometry.pointInPolygon(point, path)) continue;

        let running = 0;
        let kind = layers[0].symbol;
        for (let index = 0; index < layers.length; index += 1) {
          running += shares[index];
          if (pick <= running) {
            kind = layers[index].symbol;
            break;
          }
        }

        points.push({ x, y, kind, angle, size, variant });
      }
    }

    // Сетка даёт примерно `count` точек; лишние прореживаем равномерно по
    // списку, чтобы не срезать низ полигона.
    if (points.length > count) {
      const thinned: MapDecorPoint[] = [];
      const step = count / points.length;
      let carry = 0;
      for (const point of points) {
        carry += step;
        if (carry >= 1) {
          carry -= 1;
          thinned.push(point);
        }
      }

      return thinned;
    }

    return points;
  }
}

/** Границы полигона. */
function bounds(path: MapPoint[]): [number, number, number, number] {
  let minX = Number.POSITIVE_INFINITY;
  let minY = Number.POSITIVE_INFINITY;
  let maxX = Number.NEGATIVE_INFINITY;
  let maxY = Number.NEGATIVE_INFINITY;
  for (const [x, y] of path) {
    minX = Math.min(minX, x);
    minY = Math.min(minY, y);
    maxX = Math.max(maxX, x);
    maxY = Math.max(maxY, y);
  }

  return [minX, minY, maxX, maxY];
}

/** Компактный хеш пути: правка геометрии меняет ключ кэша. */
function pathHash(path: MapPoint[]): number {
  let hash = 0x811c9dc5;
  for (const [x, y] of path) {
    hash = Math.imul(hash ^ Math.round(x * 100), 0x01000193);
    hash = Math.imul(hash ^ Math.round(y * 100), 0x01000193);
  }

  return hash >>> 0;
}

function clamp(value: number, range: { min: number; max: number }): number {
  const number = Math.round(Number(value));
  if (!Number.isFinite(number)) return range.min;

  return Math.min(range.max, Math.max(range.min, number));
}

/** Тот же генератор, что раскладывает рябь течения. */
function seededRandom(seed: number): () => number {
  let state = seed >>> 0;

  return () => {
    state = (state + 0x6d2b79f5) >>> 0;
    let value = state;
    value = Math.imul(value ^ (value >>> 15), value | 1);
    value ^= value + Math.imul(value ^ (value >>> 7), value | 61);

    return ((value ^ (value >>> 14)) >>> 0) / 4294967296;
  };
}
