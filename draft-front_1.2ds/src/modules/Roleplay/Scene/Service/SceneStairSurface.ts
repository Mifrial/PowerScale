import { Texture } from 'pixi.js';
import { SCENE_STAIRS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_STAIRS';
import type { SceneTerrainPatternService } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import type {
  SceneTerrainMaterial,
  SceneTerrainTextureService,
} from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';

/** Материал подложки марша: у него есть фототекстура пола. */
export type SceneStairMaterial = 'wood' | 'stone' | 'tile';

/** Материалы подложки, которые понимает рисунок. */
const STAIR_MATERIALS: readonly SceneStairMaterial[] = ['wood', 'stone', 'tile'];

/** Пикселей на единицу сцены в текстуре подложки. */
const SURFACE_SCALE = 2;

/** Предел длинной стороны текстуры: марш не должен раздувать память. */
const SURFACE_LIMIT = 1024;

/** Сколько подложек держать в кэше: лишние вытесняются. */
const SURFACE_CACHE = 32;

/** Срез материала под одну доску или плиту, пиксели источника. */
interface BoardCrop {
  x: number;
  y: number;
  w: number;
  h: number;
  /** Картинка материала: фото пола или процедурный тайл. */
  source: CanvasImageSource;
}

/** Геометрия марша, по которой строится подложка. */
export interface SceneStairSurfaceShape {
  w: number;
  h: number;
}

/**
 * Материал подложки с проверкой: неизвестный материал — доски.
 *
 * Материал приходит из `props.material`; старые сцены без поля получают
 * дерево, как пол дома.
 */
export function sceneStairMaterial(material: string | undefined): SceneStairMaterial {
  return STAIR_MATERIALS.find((value) => value === material) ?? 'wood';
}

/** Число ступеней марша по подъёму; без подъёма — одна плита. */
export function sceneStairSteps(rise: number, resolution: number): number {
  if (rise <= 0) {
    return 1;
  }

  const riser = Math.max(1, Math.max(1, resolution) * SCENE_STAIRS.riserIpari);

  return Math.max(2, Math.min(24, Math.round(rise / riser)));
}

/**
 * Подложка марша: материал пола, разложенный досками по проступям.
 *
 * Текстура строится на марш целиком: каждая проступь — отдельная доска, которая
 * идёт от края до края, а волокна лежат вдоль ширины. Так рисунок не зависит от
 * поворота элемента (локальная заливка поворачивается вместе с маршем) и от
 * зума: доски выкладываются один раз на геометрию. Срезы берутся из фототекстуры
 * пола без стыков, а пока фото не загрузилось — из процедурного тайла. Без
 * канвы (тесты) подложки нет, и марш остаётся ровным тоном.
 */
export class SceneStairSurfaceService {
  /** Срезы материала по индексу доски; `null` — материал не разложить. */
  private readonly crops = new Map<SceneStairMaterial, BoardCrop[] | null>();

  /** Готовые подложки по геометрии и материалу. */
  private readonly cache = new Map<string, Texture | null>();

  constructor(
    private readonly patterns: SceneTerrainPatternService,
    private readonly materials: SceneTerrainTextureService,
  ) {}

  /** Текстура подложки или `null`, если её не из чего собрать. */
  surface(
    material: string | undefined,
    shape: SceneStairSurfaceShape,
    rise: number,
    resolution: number,
  ): Texture | null {
    const kind = sceneStairMaterial(material);
    const steps = sceneStairSteps(rise, resolution);
    const key = `${kind}:${Math.round(shape.w)}x${Math.round(shape.h)}:${steps}`;
    const cached = this.cache.get(key);
    if (cached !== undefined) {
      return cached;
    }

    const texture = this.render(kind, shape, steps);
    this.cache.set(key, texture);
    while (this.cache.size > SURFACE_CACHE) {
      const oldest = this.cache.keys().next();
      if (oldest.done === true) {
        break;
      }
      this.cache.get(oldest.value)?.destroy(true);
      this.cache.delete(oldest.value);
    }

    return texture;
  }

  /** Освобождает подложки и срезы материалов. */
  destroy(): void {
    this.cache.forEach((texture) => texture?.destroy(true));
    this.cache.clear();
    this.crops.clear();
  }

  /** Рисует подложку: доска на проступь, рисунок вдоль ширины марша. */
  private render(kind: SceneStairMaterial, shape: SceneStairSurfaceShape, steps: number): Texture | null {
    if (typeof document === 'undefined') {
      return null;
    }

    const crops = this.boardCrops(kind);
    if (crops === null || crops.length === 0) {
      return null;
    }

    const long = Math.max(1, Math.max(shape.w, shape.h));
    const scale = Math.min(SURFACE_SCALE, SURFACE_LIMIT / long);
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(shape.w * scale));
    canvas.height = Math.max(1, Math.round(shape.h * scale));
    const context = canvas.getContext('2d');
    if (context === null) {
      return null;
    }

    const color = (value: number): string => `#${value.toString(16).padStart(6, '0')}`;
    context.fillStyle = color(SCENE_STAIRS.baseColor);
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';

    const horizontal = shape.w >= shape.h;
    const run = horizontal ? shape.w : shape.h;
    const tread = run / steps;
    const short = Math.min(shape.w, shape.h);

    for (let index = 0; index < steps; index += 1) {
      const crop = crops[index % crops.length];
      // Доска лежит поперёк марша: у горизонтального марша рисунок повёрнут.
      // Срез подрезаем под пропорции проступи, чтобы доска не перекашивалась.
      const aspect = short / tread;
      const cropW = Math.max(8, Math.min(crop.w, Math.round(crop.h * aspect)));
      const cropX = crop.x + (crop.w - cropW) / 2;
      const length = short * scale;
      const depth = tread * scale;
      const center = (index + 0.5) * depth;
      context.save();
      if (horizontal) {
        context.translate(center, canvas.height / 2);
        context.rotate(-Math.PI / 2);
        context.drawImage(crop.source, cropX, crop.y, cropW, crop.h, -length / 2, -depth / 2, length, depth);
      } else {
        context.translate(canvas.width / 2, center);
        context.drawImage(crop.source, cropX, crop.y, cropW, crop.h, -length / 2, -depth / 2, length, depth);
      }
      context.restore();
    }

    return Texture.from(canvas);
  }

  /** Срезы одной доски материала: фототекстура, иначе процедурный тайл. */
  private boardCrops(kind: SceneStairMaterial): BoardCrop[] | null {
    const cached = this.crops.get(kind);
    if (cached !== undefined) {
      return cached;
    }

    const photo = this.materials.texture(kind as SceneTerrainMaterial);
    const texture = photo ?? this.patterns.texture(kind);
    const rows = photo === null ? (SCENE_STAIRS.fallbackRows[kind] ?? 0) : (SCENE_STAIRS.materialRows[kind] ?? 0);
    const crops = this.analyze(texture, rows);
    this.crops.set(kind, crops);

    return crops;
  }

  /** Ищет в материале срезы без стыков: по срезу на доску. */
  private analyze(texture: Texture, rows: number): BoardCrop[] | null {
    if (typeof document === 'undefined') {
      return null;
    }

    const source = texture.source.resource as CanvasImageSource | undefined;
    if (source === undefined || source === null) {
      return null;
    }

    const width = Math.max(1, texture.width);
    const height = Math.max(1, texture.height);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (context === null) {
      return null;
    }

    context.drawImage(source, 0, 0, width, height);
    let pixels: Uint8ClampedArray;
    try {
      pixels = context.getImageData(0, 0, width, height).data;
    } catch {
      return null;
    }

    // Камень и плитка: срез из сетки, рядов досок там нет.
    if (rows <= 0) {
      const cells = 3;
      const size = Math.floor(Math.min(width, height) / cells);
      const crops: BoardCrop[] = [];
      for (let row = 0; row < cells; row += 1) {
        for (let column = 0; column < cells; column += 1) {
          crops.push({
            x: Math.round(column * (width - size) / (cells - 1)),
            y: Math.round(row * (height - size) / (cells - 1)),
            w: size,
            h: size,
            source,
          });
        }
      }

      return crops;
    }

    const band = height / rows;
    const crops: BoardCrop[] = [];
    for (let row = 0; row < rows; row += 1) {
      const top = Math.round(row * band + band * 0.16);
      const bottom = Math.round((row + 1) * band - band * 0.16);
      const bandHeight = Math.max(4, bottom - top);
      // Ищем широкий срез без стыков: под пропорции проступи его подрежет рисунок.
      const slice = Math.max(24, Math.min(width, Math.round(bandHeight * 8)));
      const x = sceneCleanWindow(pixels, slice, width, top, bandHeight, 0.85);
      crops.push({ x, y: top, w: slice, h: bandHeight, source });
    }

    return crops;
  }
}

/**
 * Окно среза без вертикальных стыков.
 *
 * Стык доски в фото — узкая тёмная колонка. Ищем окно, где таких колонок нет:
 * иначе на проступи читалась бы не одна доска, а две. Возвращает левый край
 * окна в пикселях источника.
 */
export function sceneCleanWindow(
  pixels: Uint8ClampedArray,
  slice: number,
  width: number,
  top: number,
  height: number,
  threshold = 0.85,
): number {
  const columns = new Float32Array(width);
  for (let x = 0; x < width; x += 1) {
    let sum = 0;
    for (let y = top; y < top + height; y += 1) {
      const index = (y * width + x) * 4;
      sum += pixels[index] * 0.3 + pixels[index + 1] * 0.59 + pixels[index + 2] * 0.11;
    }
    columns[x] = sum / Math.max(1, height);
  }

  let best = 0;
  let bestScore = Number.POSITIVE_INFINITY;
  const last = Math.max(0, width - slice);
  for (let start = 0; start <= last; start += 4) {
    let score = 0;
    for (let x = start + 2; x < start + slice - 2; x += 1) {
      const local = (columns[Math.max(0, x - 6)] + columns[Math.min(width - 1, x + 6)]) / 2;
      if (columns[x] < local * threshold) {
        score += local - columns[x];
      }
    }
    // Предпочитаем середину: у края среза чаще попадается кромка доски.
    score += Math.abs(start + slice / 2 - width / 2) * 0.01;
    if (score < bestScore) {
      bestScore = score;
      best = start;
    }
  }

  return best;
}
