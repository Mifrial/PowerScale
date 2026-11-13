import { Sprite } from 'pixi.js';
import type { Container } from 'pixi.js';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneGrassTuft } from '@/modules/Roleplay/Scene/Dto/SceneGrassTuft';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import { SCENE_GRASS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_GRASS';
import { SCENE_TERRAIN_BLEND } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_BLEND';
import type { SceneGeometryService, SceneShapeProbe } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import type { SceneGrassTextureService } from '@/modules/Roleplay/Scene/Service/SceneGrassTextureService';

/** Препятствие раскладки: подготовленная фигура и запас перекрытия. */
interface GrassBlocker {
  bounds: SceneBounds;
  margin: number;
  probe: SceneShapeProbe;
}

/** Настройки одного слоя пучков. */
interface TuftLayer {
  heightIpari: number;
  spacingIpari: number;
  limit: number;
}

/**
 * Пучки травы на поле.
 *
 * Два слоя по роли: короткий ковёр лежит на земле и не участвует в сортировке,
 * высокие пучки перекрывают то, что стоит на земле. Раскладка детерминированная:
 * сетка с джиттером внутри травяных фигур. Точки, перекрытые более поздним
 * террейном (песок, вода), его кромкой и осыпью, отбрасываются.
 */
export class SceneGrassService {
  /** Кэш раскладки: пересборка сцены не должна пересеивать луг. */
  private readonly cache = new Map<string, SceneGrassTuft[]>();

  constructor(
    private readonly geometry: SceneGeometryService,
    private readonly textures: SceneGrassTextureService,
  ) {}

  /** Короткий ковёр: один меш, лежит под декором. */
  placeCarpet(elements: readonly SceneElement[], board: SceneBounds, resolution: number): SceneGrassTuft[] {
    return this.scatter(elements, board, resolution, {
      heightIpari: SCENE_GRASS.carpetHeightIpari,
      spacingIpari: SCENE_GRASS.carpetSpacingIpari,
      limit: SCENE_GRASS.carpetLimit,
    });
  }

  /** Высокие пучки: спрайты в Y-сортировке с декором и акторами. */
  placeClumps(elements: readonly SceneElement[], board: SceneBounds, resolution: number): SceneGrassTuft[] {
    return this.scatter(elements, board, resolution, {
      heightIpari: SCENE_GRASS.clumpHeightIpari,
      spacingIpari: SCENE_GRASS.clumpSpacingIpari,
      limit: SCENE_GRASS.clumpLimit,
    });
  }

  /** Рисует высокий пучок в слой; якорь — снизу по центру. */
  paint(layer: Container, tuft: SceneGrassTuft): Sprite | null {
    const texture = this.textures.frame(tuft.variant);
    if (texture === null) {
      return null;
    }

    const sprite = new Sprite(texture);
    sprite.anchor.set(0.5, 1);
    sprite.position.set(tuft.x, tuft.y);
    sprite.width = tuft.height * SCENE_GRASS.tuftAspect;
    sprite.height = tuft.height;
    sprite.tint = tuft.tint;
    sprite.alpha = tuft.alpha;
    sprite.rotation = tuft.rotation;
    if (tuft.flip) {
      sprite.scale.x *= -1;
    }

    layer.addChild(sprite);

    return sprite;
  }

  /** Общая раскладка слоя пучков. */
  private scatter(
    elements: readonly SceneElement[],
    board: SceneBounds,
    resolution: number,
    layer: TuftLayer,
  ): SceneGrassTuft[] {
    const base = Math.max(1, resolution);
    // Раскладка не зависит от зума: иначе трава появляется только после
    // пересборки сцены и пропадает при отдалении. Плотность задаёт шаг в
    // мировых единицах, а масштаб делает камера.
    const key = this.cacheKey(elements, board, base, layer);
    const cached = this.cache.get(key);
    if (cached !== undefined) {
      return cached;
    }

    const tufts = this.scatterNow(elements, board, base, layer);
    this.remember(key, tufts);

    return tufts;
  }

  /** Раскладка без кэша: общий проход по травяным фигурам. */
  private scatterNow(
    elements: readonly SceneElement[],
    board: SceneBounds,
    base: number,
    layer: TuftLayer,
  ): SceneGrassTuft[] {
    const spacing = base * layer.spacingIpari;
    const spill = base * SCENE_TERRAIN_BLEND.spillIpari;
    const tufts: SceneGrassTuft[] = [];
    for (let order = 0; order < elements.length; order += 1) {
      const element = elements[order];
      if (element.props.kind !== 'grass') {
        continue;
      }

      const blockers = elements.slice(order + 1).map((blocker) => {
        const probe = this.geometry.probe(blocker.shape);

        return {
          bounds: probe.bounds,
          margin: this.blockMargin(blocker, spill),
          probe,
        };
      });
      const area = this.geometry.probe(element.shape);
      const bounds = area.bounds;
      const minX = Math.max(board.x, bounds.x);
      const maxX = Math.min(board.x + board.width, bounds.x + bounds.width);
      const minY = Math.max(board.y, bounds.y);
      const maxY = Math.min(board.y + board.height, bounds.y + bounds.height);
      if (maxX <= minX || maxY <= minY) {
        continue;
      }

      const startX = Math.floor(minX / spacing) * spacing;
      const startY = Math.floor(minY / spacing) * spacing;
      for (let gridX = startX; gridX <= maxX; gridX += spacing) {
        for (let gridY = startY; gridY <= maxY; gridY += spacing) {
          const point = this.jitter(gridX, gridY, spacing, element.id);
          if (point[0] < minX || point[0] > maxX || point[1] < minY || point[1] > maxY) {
            continue;
          }

          if (!area.contains(point, 0) || this.blocked(blockers, point)) {
            continue;
          }

          tufts.push(this.tuft(element.id, point, tufts.length, base, layer.heightIpari));
          if (tufts.length >= layer.limit) {
            return tufts;
          }
        }
      }
    }

    return tufts;
  }

  /** Запас перекрытия: кромка элемента и рваная осыпь. */
  private blockMargin(element: SceneElement, spill: number): number {
    const shore = (element.props.shoreKind ?? 'none') === 'none' ? 0 : (element.props.shoreWidth ?? 0);

    return spill + shore;
  }

  /** Точка перекрыта более поздним террейном. */
  private blocked(blockers: readonly GrassBlocker[], point: ScenePoint): boolean {
    for (const blocker of blockers) {
      const bounds = blocker.bounds;
      if (point[0] < bounds.x - blocker.margin || point[0] > bounds.x + bounds.width + blocker.margin) {
        continue;
      }

      if (point[1] < bounds.y - blocker.margin || point[1] > bounds.y + bounds.height + blocker.margin) {
        continue;
      }

      if (blocker.probe.contains(point, blocker.margin)) {
        return true;
      }
    }

    return false;
  }

  /** Ключ кэша: поле, разрешение, полоса плотности и форма элементов. */
  private cacheKey(
    elements: readonly SceneElement[],
    board: SceneBounds,
    base: number,
    layer: TuftLayer,
  ): string {
    const shapes = elements.map(
      (element) =>
        `${element.id}:${element.props.kind}:${element.props.shoreKind ?? 'none'}:${element.props.shoreWidth ?? 0}:${JSON.stringify(element.shape)}`,
    );

    return `${layer.spacingIpari}|${layer.heightIpari}|${base}|${board.x},${board.y},${board.width},${board.height}|${shapes.join(';')}`;
  }

  /** Запоминает раскладку, вытесняя самую старую. */
  private remember(key: string, tufts: SceneGrassTuft[]): void {
    // Держим ковёр и пучки двух соседних полос: дальше выгоднее пересеять,
    // чем хранить десятки тысяч объектов на каждую полосу.
    if (this.cache.size >= 4) {
      const oldest = this.cache.keys().next().value;
      if (oldest !== undefined) {
        this.cache.delete(oldest);
      }
    }

    this.cache.set(key, tufts);
  }

  /** Точка внутри ячейки: джиттер детерминирован от элемента и ячейки. */
  private jitter(gridX: number, gridY: number, spacing: number, elementId: number): ScenePoint {
    const cellX = Math.round(gridX * 16);
    const cellY = Math.round(gridY * 16);
    const jitter = spacing * SCENE_GRASS.tuftJitter;
    const randomX = this.hash(elementId, cellX, cellY) / 4294967296;
    const randomY = this.hash(elementId + 7919, cellX, cellY) / 4294967296;

    return [gridX + (randomX - 0.5) * jitter, gridY + (randomY - 0.5) * jitter];
  }

  /** Пучок: вариант, высота, отражение и вариация цвета. */
  private tuft(
    elementId: number,
    point: ScenePoint,
    sequence: number,
    base: number,
    heightIpari: number,
  ): SceneGrassTuft {
    const seed = this.hash(elementId, Math.round(point[0] * 64), Math.round(point[1] * 64));
    const scale =
      SCENE_GRASS.tuftScaleMin + (((seed >>> 8) % 256) / 256) * (SCENE_GRASS.tuftScaleMax - SCENE_GRASS.tuftScaleMin);
    const shade = 0.88 + (((seed >>> 24) % 256) / 256) * 0.12;
    // Тёплый и холодный подмес: одинаковые пучки не читаются штампом.
    const kind = (seed >>> 20) % 3;
    const warm = kind === 1 ? [1, 0.96, 0.78] : kind === 2 ? [0.84, 0.92, 0.8] : [1, 1, 1];

    return {
      x: point[0],
      y: point[1],
      variant: seed % SCENE_GRASS.tuftVariants,
      height: base * heightIpari * scale,
      flip: ((seed >>> 16) & 1) === 1,
      rotation: (((seed >>> 12) % 128) / 128) * 0.16 - 0.08,
      tint:
        (this.channel(shade * warm[0]) << 16) |
        (this.channel(shade * warm[1]) << 8) |
        this.channel(shade * warm[2]),
      alpha: 0.85 + (((seed >>> 28) % 16) / 16) * 0.15,
      sequence,
    };
  }

  /** Канал цвета 0…255. */
  private channel(amount: number): number {
    return Math.min(255, Math.round(amount * 255));
  }

  /** Целочисленный хеш: детерминированный разброс без общего состояния. */
  private hash(a: number, b: number, c: number): number {
    let hash = Math.imul(a, 374761393) + Math.imul(b, 668265263) + Math.imul(c, 1274126177);
    hash = Math.imul(hash ^ (hash >>> 13), 1274126177);

    return (hash ^ (hash >>> 16)) >>> 0;
  }
}
