import { Rectangle, Texture } from 'pixi.js';
import { SCENE_GRASS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_GRASS';
import type { SceneTerrainPatternService } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import type { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';

/**
 * Атлас пучков травы.
 *
 * Пучки рисуются процедурно на canvas: лезвия с градиентом от тёмного низа к
 * светлому кончику, зерно — фрагмент того же фотоматериала, что и земля, плюс
 * мягкая тень у основания. Один атлас на все варианты, поэтому тысячи спрайтов
 * уходят в один батч.
 */
export class SceneGrassTextureService {
  private atlas: Texture | null = null;

  private frames: Texture[] = [];

  private rects: (readonly [number, number, number, number])[] = [];

  constructor(
    private readonly materials: SceneTerrainTextureService,
    private readonly patterns: SceneTerrainPatternService,
  ) {}

  /** Текстура варианта пучка; `null` — атлас не собрался. */
  frame(variant: number): Texture | null {
    if (this.atlas === null) {
      this.build();
    }

    return this.frames[variant % Math.max(1, this.frames.length)] ?? null;
  }

  /** Атлас целиком: нужен шейдеру ковра. */
  atlasTexture(): Texture | null {
    if (this.atlas === null) {
      this.build();
    }

    return this.atlas;
  }

  /** Нормализованные UV-рамки вариантов: по ним собирается ковёр. */
  frameRects(): readonly (readonly [number, number, number, number])[] {
    if (this.atlas === null) {
      this.build();
    }

    return this.rects;
  }

  /** Освобождает атлас и кадры. */
  destroy(): void {
    this.frames.forEach((frame) => frame.destroy(false));
    this.frames = [];
    this.rects = [];
    this.atlas?.destroy(true);
    this.atlas = null;
  }

  /** Собирает атлас: варианты в ряд. */
  private build(): void {
    const cell = SCENE_GRASS.tuftCellPixels;
    const variants = SCENE_GRASS.tuftVariants;
    const canvas = document.createElement('canvas');
    canvas.width = cell * variants;
    canvas.height = cell;
    const context = canvas.getContext('2d');
    if (context === null) {
      return;
    }

    const grain = this.grain();
    for (let variant = 0; variant < variants; variant += 1) {
      this.paintClump(context, variant * cell, cell, variant, grain);
    }

    const atlas = Texture.from(canvas);
    atlas.source.style.addressMode = 'clamp-to-edge';
    atlas.source.style.mipmapFilter = 'linear';
    atlas.source.style.maxAnisotropy = 4;
    atlas.source.style.update();
    atlas.source.autoGenerateMipmaps = true;
    this.atlas = atlas;
    this.frames = Array.from(
      { length: variants },
      (_, index) =>
        new Texture({
          source: atlas.source,
          frame: new Rectangle(index * cell, 0, cell, cell),
        }),
    );
    const width = cell * variants;
    this.rects = Array.from(
      { length: variants },
      (_, index) =>
        [(index * cell) / width, 0, ((index + 1) * cell) / width, 1] as const,
    );
  }

  /** Зерно фотоматериала: тот же тайл, что у земли. */
  private grain(): CanvasPattern | null {
    const texture = this.materials.texture('grass') ?? this.patterns.texture('grass');
    const resource = texture.source.resource;
    const isImageBitmap = typeof ImageBitmap !== 'undefined' && resource instanceof ImageBitmap;
    if (!(resource instanceof HTMLImageElement) && !(resource instanceof HTMLCanvasElement) && !isImageBitmap) {
      return null;
    }

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    return context === null ? null : context.createPattern(resource, 'repeat');
  }

  /** Рисует один пучок в ячейку атласа. */
  private paintClump(
    context: CanvasRenderingContext2D,
    offsetX: number,
    cell: number,
    variant: number,
    grain: CanvasPattern | null,
  ): void {
    const random = this.random(variant * 7919 + 104729);
    const baseX = offsetX + cell / 2;
    const baseY = cell * 0.94;

    // Тень под пучком: без неё спрайт висит над землёй.
    const shadowRadius = cell * 0.32;
    const shadow = context.createRadialGradient(baseX, baseY, 0, baseX, baseY, shadowRadius);
    shadow.addColorStop(0, 'rgba(10, 20, 8, 0.18)');
    shadow.addColorStop(1, 'rgba(10, 20, 8, 0)');
    context.fillStyle = shadow;
    context.fillRect(baseX - shadowRadius, baseY - shadowRadius, shadowRadius * 2, shadowRadius * 2);

    // Три пучка в основании: силуэт получается кустистым, а не веером из
    // одной точки.
    const clusters = [-0.16, 0.02, 0.18];
    for (let blade = 0; blade < SCENE_GRASS.tuftBlades; blade += 1) {
      const cluster = clusters[blade % clusters.length];
      const startX = baseX + (cluster + (random() - 0.5) * 0.14) * cell;
      const startY = baseY - random() * cell * 0.1;
      const height = cell * (0.3 + random() * 0.55);
      const tipX = startX + (random() - 0.5) * cell * 0.3;
      const tipY = startY - height;
      const width = cell * (0.03 + random() * 0.03);
      const light = 0.82 + random() * 0.23;
      const base = SCENE_GRASS.tuftBaseColor;
      const tip = SCENE_GRASS.tuftTipColor;
      const gradient = context.createLinearGradient(startX, startY, tipX, tipY);
      gradient.addColorStop(0, `rgb(${base[0]}, ${base[1]}, ${base[2]})`);
      gradient.addColorStop(
        1,
        `rgb(${this.mix(tip[0], light)}, ${this.mix(tip[1], light)}, ${this.mix(tip[2], light)})`,
      );
      context.fillStyle = gradient;

      const dx = tipX - startX;
      const dy = tipY - startY;
      const length = Math.hypot(dx, dy) || 1;
      const normalX = (-dy / length) * (width / 2);
      const normalY = (dx / length) * (width / 2);
      context.beginPath();
      context.moveTo(startX - normalX, startY - normalY);
      context.lineTo(startX + normalX, startY + normalY);
      context.lineTo(tipX, tipY);
      context.closePath();
      context.fill();
    }

    if (grain !== null) {
      context.save();
      context.globalCompositeOperation = 'source-atop';
      context.globalAlpha = 0.28;
      context.fillStyle = grain;
      context.fillRect(offsetX, 0, cell, cell);
      context.restore();
    }
  }

  /** Доля светлого оттенка у кончика: 0.82…1.05, с потолком 255. */
  private mix(channel: number, amount: number): number {
    return Math.min(255, Math.round(channel * amount));
  }

  /** Детерминированный PRNG. */
  private random(seed: number): () => number {
    let state = seed >>> 0;

    return (): number => {
      state = (state + 0x6d2b79f5) >>> 0;
      let value = state;
      value = Math.imul(value ^ (value >>> 15), value | 1);
      value ^= value + Math.imul(value ^ (value >>> 7), value | 61);

      return ((value ^ (value >>> 14)) >>> 0) / 4294967296;
    };
  }
}
