import { Texture } from 'pixi.js';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

/** Ширина мелководной полосы у берега в ипари. */
const SHALLOW_BAND_IPARI = 0.55;

/** Разрешение запекания по длинной стороне. */
const MAX_CELLS = 160;

/** Минимальная сторона сетки. */
const MIN_CELLS = 2;

/** Цвет мелководья у берега. */
const SHALLOW: [number, number, number] = [0x86, 0xc3, 0xd4];

/** Цвет глубокой воды. */
const DEEP: [number, number, number] = [0x1f, 0x54, 0x70];

/** Прозрачность воды у самого уреза и на глубине. */
const EDGE_ALPHA = 0.3;
const DEEP_ALPHA = 0.88;

/** Амплитуда шума глубины в ипари: кромка мелководья гуляет вдоль берега. */
const NOISE_IPARI = 0.4;

/** Ширина мягкого подъёма прозрачности у уреза, ипари. */
const EDGE_FADE_IPARI = 0.38;

/** Поле глубины водоёма: сетка расстояний плюс готовая текстура градиента. */
export interface SceneWaterDepth {
  bounds: SceneBounds;
  /** Размер клетки в единицах сцены. */
  cell: number;
  cols: number;
  rows: number;
  /** Расстояние до берега в единицах сцены, с шумом; вне воды — ноль. */
  depth: Float32Array;
  /** Готовый градиент глубины с прозрачностью по силуэту. */
  texture: Texture;
}

/**
 * Поле глубины воды.
 *
 * Считается один раз на элемент и пересчитывается только при смене геометрии:
 * расстояние до берега даёт плавный градиент без полос, ширину мелководья,
 * кромку пены и глубину под объектом. Это единственный источник правды о том,
 * насколько глубоко в воде стоит дерево, камень или персонаж.
 */
export class SceneWaterDepthService {
  private readonly cache = new Map<number, { key: string; field: SceneWaterDepth }>();

  /** Поле элемента; повторный вызов возвращает закэшированное. */
  field(element: SceneElement, resolution: number): SceneWaterDepth {
    const key = `${Math.max(1, resolution)}|${JSON.stringify(element.shape)}`;
    const cached = this.cache.get(element.id);
    if (cached !== undefined && cached.key === key) {
      return cached.field;
    }

    cached?.field.texture.destroy(true);
    const field = this.bake(element, resolution);
    this.cache.set(element.id, { key, field });

    return field;
  }

  /** Глубина в точке в единицах сцены; вне водоёма — ноль. */
  depthAt(element: SceneElement, resolution: number, point: ScenePoint): number {
    const field = this.field(element, resolution);
    const column = Math.round((point[0] - field.bounds.x) / field.cell - 0.5);
    const row = Math.round((point[1] - field.bounds.y) / field.cell - 0.5);
    if (column < 0 || row < 0 || column >= field.cols || row >= field.rows) {
      return 0;
    }

    return field.depth[row * field.cols + column];
  }

  /** Освобождает текстуры и кэш. */
  destroy(): void {
    this.cache.forEach(({ field }) => field.texture.destroy(true));
    this.cache.clear();
  }

  private bake(element: SceneElement, resolution: number): SceneWaterDepth {
    const bounds = sceneGeometryService.bounds(element.shape);
    const width = Math.max(1, bounds.width);
    const height = Math.max(1, bounds.height);
    const longest = Math.max(width, height);
    const cell = Math.max(1, longest / MAX_CELLS);
    const cols = Math.max(MIN_CELLS, Math.ceil(width / cell));
    const rows = Math.max(MIN_CELLS, Math.ceil(height / cell));
    const scaleX = 1 / cell;
    const scaleY = 1 / cell;

    const mask = this.rasterize(element.shape, bounds, cols, rows);
    const distance = this.distanceTransform(mask, cols, rows);
    const noise = this.noiseOf(element.id);
    // Цвет уходит в глубину уже в пределах мелководной полосы: иначе узкая
    // река остаётся бледной, а глубина читается только в одной точке.
    const limit = Math.max(1, resolution * SHALLOW_BAND_IPARI);
    const amplitude = Math.max(1, resolution * NOISE_IPARI);
    const depth = new Float32Array(cols * rows);
    const canvas = document.createElement('canvas');
    canvas.width = cols;
    canvas.height = rows;
    const context = canvas.getContext('2d');
    const image = context?.createImageData(cols, rows);
    const data = image?.data;

    for (let row = 0; row < rows; row += 1) {
      for (let column = 0; column < cols; column += 1) {
        const index = row * cols + column;
        const raw = mask[index];
        const worldX = bounds.x + (column + 0.5) / scaleX;
        const worldY = bounds.y + (row + 0.5) / scaleY;
        const value = raw > 0 ? Math.max(0, distance[index] * cell + noise(worldX, worldY) * amplitude) : 0;
        depth[index] = value;

        if (data === undefined) {
          continue;
        }

        const t = Math.min(1, value / limit);
        const color = this.mix(t);
        // У самого уреза альфа нулевая: иначе по берегу идёт ровный светлый
        // ободок, который стоит на месте и читается как лёд. Кромку рисует
        // отдельный «язык воды», который ходит вместе с накатом.
        const edge = this.smooth(value / Math.max(1, resolution * EDGE_FADE_IPARI));
        const alpha = edge * (EDGE_ALPHA + (DEEP_ALPHA - EDGE_ALPHA) * this.smooth(t));
        data[index * 4] = color[0];
        data[index * 4 + 1] = color[1];
        data[index * 4 + 2] = color[2];
        data[index * 4 + 3] = Math.round(alpha * 255 * raw);
      }
    }

    let texture: Texture;
    if (context !== null && image !== undefined) {
      context.putImageData(image, 0, 0);
      texture = Texture.from(canvas);
      texture.source.scaleMode = 'linear';
    } else {
      texture = Texture.WHITE;
    }

    return { bounds, cell, cols, rows, depth, texture };
  }

  /** Растровая маска силуэта: 1 внутри, 0 снаружи, с мягким краем. */
  private rasterize(shape: SceneShape, bounds: SceneBounds, cols: number, rows: number): Float32Array {
    const canvas = document.createElement('canvas');
    canvas.width = cols;
    canvas.height = rows;
    const context = canvas.getContext('2d');
    const mask = new Float32Array(cols * rows);
    if (context === null) {
      return mask;
    }

    const scaleX = cols / Math.max(1, bounds.width);
    const scaleY = rows / Math.max(1, bounds.height);
    const toX = (value: number): number => (value - bounds.x) * scaleX;
    const toY = (value: number): number => (value - bounds.y) * scaleY;
    context.fillStyle = '#fff';
    context.beginPath();
    if (shape.type === 'rect') {
      context.rect(toX(shape.x), toY(shape.y), shape.w * scaleX, shape.h * scaleY);
    } else if (shape.type === 'circle') {
      context.ellipse(toX(shape.cx), toY(shape.cy), shape.r * scaleX, shape.r * scaleY, 0, 0, Math.PI * 2);
    } else {
      const contour = this.contour(shape);
      if (contour.length < 3) {
        return mask;
      }

      context.moveTo(toX(contour[0][0]), toY(contour[0][1]));
      for (let index = 1; index < contour.length; index += 1) {
        context.lineTo(toX(contour[index][0]), toY(contour[index][1]));
      }

      context.closePath();
    }

    context.fill();

    const pixels = context.getImageData(0, 0, cols, rows).data;
    for (let index = 0; index < cols * rows; index += 1) {
      mask[index] = pixels[index * 4 + 3] / 255;
    }

    return mask;
  }

  /** Контур фигуры в мировых координатах. */
  private contour(shape: SceneShape): ScenePoint[] {
    const tolerance = sceneCurveService.toleranceFor(1);
    if (shape.type === 'path') {
      return sceneRibbonService.outline(shape.points, shape.handles ?? [], shape.widths, tolerance);
    }

    if (shape.type === 'polygon') {
      return sceneCurveService.flattenContour(shape.points, shape.handles ?? [], tolerance);
    }

    return [];
  }

  /** Дистанционное преобразование Шама: расстояние до границы в клетках. */
  private distanceTransform(mask: Float32Array, cols: number, rows: number): Float32Array {
    const size = cols * rows;
    const distance = new Float32Array(size);
    const horizontal = 1;
    const diagonal = Math.SQRT2;
    for (let index = 0; index < size; index += 1) {
      distance[index] = mask[index] > 0.5 ? Number.POSITIVE_INFINITY : 0;
    }

    for (let row = 0; row < rows; row += 1) {
      for (let column = 0; column < cols; column += 1) {
        const index = row * cols + column;
        if (distance[index] === 0) {
          continue;
        }

        let best = distance[index];
        if (column > 0) best = Math.min(best, distance[index - 1] + horizontal);
        if (row > 0) best = Math.min(best, distance[index - cols] + horizontal);
        if (column > 0 && row > 0) best = Math.min(best, distance[index - cols - 1] + diagonal);
        if (column < cols - 1 && row > 0) best = Math.min(best, distance[index - cols + 1] + diagonal);
        distance[index] = best;
      }
    }

    for (let row = rows - 1; row >= 0; row -= 1) {
      for (let column = cols - 1; column >= 0; column -= 1) {
        const index = row * cols + column;
        if (distance[index] === 0) {
          continue;
        }

        let best = distance[index];
        if (column < cols - 1) best = Math.min(best, distance[index + 1] + horizontal);
        if (row < rows - 1) best = Math.min(best, distance[index + cols] + horizontal);
        if (column < cols - 1 && row < rows - 1) best = Math.min(best, distance[index + cols + 1] + diagonal);
        if (column > 0 && row < rows - 1) best = Math.min(best, distance[index + cols - 1] + diagonal);
        distance[index] = best;
      }
    }

    return distance;
  }

  /** Мягкий шум: несколько волн, чтобы кромка мелководья не была параллельной. */
  private noiseOf(seed: number): (x: number, y: number) => number {
    const random = (): number => {
      seed = (seed * 1664525 + 1013904223) % 4294967296;

      return seed / 4294967296;
    };
    // Длина волны — сотни единиц сцены: кромка мелководья должна гулять
    // вдоль берега длинными участками. Короткие волны давали «гребешок» из
    // полос у кромки, который читался как лёд.
    const waves = Array.from({ length: 3 }, () => {
      const frequency = 0.016 + random() * 0.022;

      return {
        fx: frequency * (random() > 0.5 ? 1 : -1) * (0.6 + random() * 0.8),
        fy: frequency * (random() > 0.5 ? 1 : -1) * (0.6 + random() * 0.8),
        phase: random() * Math.PI * 2,
      };
    });

    return (x: number, y: number): number => {
      let value = 0;
      for (const wave of waves) {
        value += Math.sin(wave.fx * x + wave.fy * y + wave.phase);
      }

      return value / waves.length;
    };
  }

  /** Цвет на глубине `t`: от мелководья к глубине. */
  private mix(t: number): [number, number, number] {
    const k = this.smooth(t);

    return [
      Math.round(SHALLOW[0] + (DEEP[0] - SHALLOW[0]) * k),
      Math.round(SHALLOW[1] + (DEEP[1] - SHALLOW[1]) * k),
      Math.round(SHALLOW[2] + (DEEP[2] - SHALLOW[2]) * k),
    ];
  }

  private smooth(t: number): number {
    const clamped = Math.min(1, Math.max(0, t));

    return clamped * clamped * (3 - 2 * clamped);
  }
}
