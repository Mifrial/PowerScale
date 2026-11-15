import { Texture } from 'pixi.js';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { signedDistanceField } from '@/modules/Roleplay/Scene/Service/SceneSurfaceDistance';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

/** Ширина мелководной полосы у берега в ипари. */
const SHALLOW_BAND_IPARI = 0.9;

/** Запас поля за урезом в ипари: место для наката и нормалей. */
const MARGIN_IPARI = 1.0;

/** Разрешение запекания по длинной стороне. */
const MAX_CELLS = 1024;

/** Минимальная сторона сетки. */
const MIN_CELLS = 2;

/** Потолок шкалы глубины в ипари: дальше цвет толщи всё равно не меняется. */
const MAX_DEPTH_IPARI = 3;

/** Продление поля за концы реки в ипари: глубина у торца остаётся полной. */
const FIELD_END_IPARI = 2;

/** Цвет мелководья у берега. */
const SHALLOW: [number, number, number] = [0x9a, 0xd0, 0xe0];

/** Цвет глубокой воды. */
const DEEP: [number, number, number] = [0x1f, 0x54, 0x70];

/** Прозрачность воды у самого уреза и на глубине. */
const EDGE_ALPHA = 0.3;
const DEEP_ALPHA = 0.88;

/** Амплитуда шума глубины в ипари: кромка мелководья гуляет вдоль берега. */
const NOISE_IPARI = 0.4;

/** Поле глубины водоёма: сетка расстояний, текстура данных и готовая рампа. */
export interface SceneWaterDepth {
  /** Границы поля: силуэт воды плюс запас под накат. */
  bounds: SceneBounds;
  /** Границы самого водоёма, без запаса. */
  water: SceneBounds;
  /** Размер клетки в единицах сцены. */
  cell: number;
  cols: number;
  rows: number;
  /** Расстояние до берега в единицах сцены, с шумом; вне воды — ноль. */
  depth: Float32Array;
  /** Масштаб знаковой дистанции в текстуре данных, единицы сцены. */
  scale: number;
  /**
   * Данные для шейдеров: R — знаковая дистанция до берега (плюс внутри),
   * G — силуэт водоёма с мягким краем.
   */
  data: Texture;
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
 *
 * Поле шире силуэта на запас: накату и нормалям нужны данные и за урезом,
 * иначе вода не сможет наехать на берег. Знаковая дистанция пишется в
 * текстуру данных — по ней шейдеры строят поглощение, кромку и преломление.
 */
export class SceneWaterDepthService {
  private readonly cache = new Map<number, { key: string; field: SceneWaterDepth }>();

  /** Поле элемента; повторный вызов возвращает закэшированное. */
  field(element: SceneElement, resolution: number, endPads: readonly [number, number] = [0, 0]): SceneWaterDepth {
    const key = `${Math.max(1, resolution)}|${endPads[0]},${endPads[1]}|${JSON.stringify(element.shape)}`;
    const cached = this.cache.get(element.id);
    if (cached !== undefined && cached.key === key) {
      return cached.field;
    }

    if (cached !== undefined) {
      if (cached.field.texture !== Texture.WHITE) {
        cached.field.texture.destroy(true);
      }
      if (cached.field.data !== Texture.WHITE) {
        cached.field.data.destroy(true);
      }
    }

    const field = this.bake(element, resolution, endPads);
    this.cache.set(element.id, { key, field });

    return field;
  }

  /** Глубина в точке в единицах сцены; вне водоёма — ноль. */
  depthAt(
    element: SceneElement,
    resolution: number,
    point: ScenePoint,
    endPads: readonly [number, number] = [0, 0],
  ): number {
    const field = this.field(element, resolution, endPads);
    const column = Math.round((point[0] - field.bounds.x) / field.cell - 0.5);
    const row = Math.round((point[1] - field.bounds.y) / field.cell - 0.5);
    if (column < 0 || row < 0 || column >= field.cols || row >= field.rows) {
      return 0;
    }

    return field.depth[row * field.cols + column];
  }

  /** Освобождает текстуры и кэш. */
  destroy(): void {
    this.cache.forEach(({ field }) => {
      if (field.texture !== Texture.WHITE) {
        field.texture.destroy(true);
      }
      if (field.data !== Texture.WHITE) {
        field.data.destroy(true);
      }
    });
    this.cache.clear();
  }

  private bake(element: SceneElement, resolution: number, endPads: readonly [number, number]): SceneWaterDepth {
    const water = sceneGeometryService.bounds(element.shape);
    // Конец реки — не берег: даже если вода обрывается внутри поля, глубина
    // не должна выклиниваться. Поле всегда продлевает оба конца, а геометрия
    // и обводка — только там, где торец надо спрятать под обрезку.
    const fieldPast = Math.max(2, resolution * FIELD_END_IPARI);
    const fieldPads: readonly [number, number] =
      element.shape.type === 'path' ? [Math.max(endPads[0], fieldPast), Math.max(endPads[1], fieldPast)] : [0, 0];
    const contour = this.contour(element.shape, fieldPads);
    const span = contour.length >= 3 ? boundsOfPoints(contour) : water;
    const margin = Math.max(2, resolution * MARGIN_IPARI);
    const originX = span.x - margin;
    const originY = span.y - margin;
    const width = Math.max(1, span.width) + margin * 2;
    const height = Math.max(1, span.height) + margin * 2;
    const cell = Math.max(1, Math.max(width, height) / MAX_CELLS);
    const cols = Math.max(MIN_CELLS, Math.ceil(width / cell));
    const rows = Math.max(MIN_CELLS, Math.ceil(height / cell));
    // Границы выровнены по сетке: текстура покрывает ровно cols×rows клеток,
    // иначе выборка по мировым координатам уезжала бы на краю поля.
    const bounds: SceneBounds = { x: originX, y: originY, width: cols * cell, height: rows * cell };

    const mask = this.rasterize(element.shape, contour, bounds, cell, cols, rows);
    const signed = signedDistanceField(mask, cols, rows);
    const noise = this.noiseOf(element.id);
    // Цвет уходит в глубину уже в пределах мелководной полосы: иначе узкая
    // река остаётся бледной, а глубина читается только в одной точке.
    const limit = Math.max(1, resolution * SHALLOW_BAND_IPARI);
    // Шкала кодирования должна покрывать всю глубину: иначе в текстуре
    // дальше мелководной полосы глубина насыщается, и поглощение в шейдере
    // не отличает середину реки от мели. Сверху шкалу режем: без потолка
    // широкий водоём отдаёт весь 8-битный диапазон на глубину, которая
    // всё равно утонула в цвете толщи, и градиент у берега идёт ступенями.
    let deepest = 0;
    for (let index = 0; index < signed.length; index += 1) {
      if (signed[index] > deepest) {
        deepest = signed[index];
      }
    }
    const scale = Math.max(limit, Math.min(deepest * cell, MAX_DEPTH_IPARI * Math.max(1, resolution)));
    const amplitude = Math.max(1, resolution * NOISE_IPARI);
    const depth = new Float32Array(cols * rows);
    const canvas = document.createElement('canvas');
    canvas.width = cols;
    canvas.height = rows;
    const context = canvas.getContext('2d');
    const image = context?.createImageData(cols, rows);
    const data = image?.data;
    const dataCanvas = document.createElement('canvas');
    dataCanvas.width = cols;
    dataCanvas.height = rows;
    const dataContext = dataCanvas.getContext('2d');
    const dataImage = dataContext?.createImageData(cols, rows);
    const field = dataImage?.data;

    for (let row = 0; row < rows; row += 1) {
      for (let column = 0; column < cols; column += 1) {
        const index = row * cols + column;
        const edge = signed[index] * cell;
        const worldX = bounds.x + (column + 0.5) * cell;
        const worldY = bounds.y + (row + 0.5) * cell;
        const value = edge > 0 ? Math.max(0, edge + noise(worldX, worldY) * amplitude) : 0;
        depth[index] = value;

        if (data !== undefined) {
          const t = Math.min(1, value / limit);
          const color = this.mix(t);
          const alpha = EDGE_ALPHA + (DEEP_ALPHA - EDGE_ALPHA) * this.smooth(t);
          data[index * 4] = color[0];
          data[index * 4 + 1] = color[1];
          data[index * 4 + 2] = color[2];
          data[index * 4 + 3] = Math.round(alpha * 255 * mask[index]);
        }

        if (field !== undefined) {
          // 0.5 — урез: знаковая дистанция хранится со знаком в R.
          field[index * 4] = Math.round(Math.min(1, Math.max(0, (edge / scale) * 0.5 + 0.5)) * 255);
          field[index * 4 + 1] = Math.round(Math.min(1, Math.max(0, mask[index])) * 255);
          field[index * 4 + 2] = 0;
          field[index * 4 + 3] = 255;
        }
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

    let dataTexture: Texture;
    if (dataContext !== null && dataImage !== undefined) {
      dataContext.putImageData(dataImage, 0, 0);
      dataTexture = Texture.from(dataCanvas);
      dataTexture.source.scaleMode = 'linear';
    } else {
      dataTexture = Texture.WHITE;
    }

    return { bounds, water, cell, cols, rows, depth, scale, data: dataTexture, texture };
  }

  /** Растровая маска силуэта: 1 внутри, 0 снаружи, с мягким краем. */
  private rasterize(
    shape: SceneShape,
    contour: ScenePoint[],
    bounds: SceneBounds,
    cell: number,
    cols: number,
    rows: number,
  ): Float32Array {
    const canvas = document.createElement('canvas');
    canvas.width = cols;
    canvas.height = rows;
    const context = canvas.getContext('2d');
    const mask = new Float32Array(cols * rows);
    if (context === null) {
      return mask;
    }

    // Клетка квадратная: иначе дистанционное преобразование считало бы
    // расстояние по разным осям по-разному.
    const scale = 1 / cell;
    const toX = (value: number): number => (value - bounds.x) * scale;
    const toY = (value: number): number => (value - bounds.y) * scale;
    context.fillStyle = '#fff';
    context.beginPath();
    if (shape.type === 'rect') {
      context.rect(toX(shape.x), toY(shape.y), shape.w * scale, shape.h * scale);
    } else if (shape.type === 'circle') {
      context.ellipse(toX(shape.cx), toY(shape.cy), shape.r * scale, shape.r * scale, 0, 0, Math.PI * 2);
    } else {
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
  private contour(shape: SceneShape, endPads: readonly [number, number]): ScenePoint[] {
    const tolerance = sceneCurveService.toleranceFor(1);
    if (shape.type === 'path') {
      return sceneRibbonService.outline(
        shape.points,
        shape.handles ?? [],
        shape.widths,
        tolerance,
        0,
        endPads[0],
        endPads[1],
      );
    }

    if (shape.type === 'polygon') {
      return sceneCurveService.flattenContour(shape.points, shape.handles ?? [], tolerance);
    }

    return [];
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

/**
 * Границы набора точек в единицах сцены.
 */
function boundsOfPoints(points: ScenePoint[]): SceneBounds {
  let minX = Number.POSITIVE_INFINITY;
  let minY = Number.POSITIVE_INFINITY;
  let maxX = Number.NEGATIVE_INFINITY;
  let maxY = Number.NEGATIVE_INFINITY;
  for (const point of points) {
    minX = Math.min(minX, point[0]);
    minY = Math.min(minY, point[1]);
    maxX = Math.max(maxX, point[0]);
    maxY = Math.max(maxY, point[1]);
  }

  return { x: minX, y: minY, width: Math.max(0, maxX - minX), height: Math.max(0, maxY - minY) };
}
