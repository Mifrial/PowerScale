import { FillPattern, Matrix, Texture } from 'pixi.js';

/** Шаблоны террейна, для которых есть процедурная текстура. */
export type SceneTerrainKind = 'grass' | 'dirt' | 'stone' | 'sand' | 'mud' | 'water' | 'ice';

/** Сторона процедурного тайла в пикселях. */
const TEXTURE_SIZE = 256;

interface Palette {
  base: [number, number, number];
  light: [number, number, number];
  dark: [number, number, number];
}

const PALETTES: Record<SceneTerrainKind, Palette> = {
  grass: {
    base: [88, 124, 48],
    light: [132, 168, 74],
    dark: [52, 82, 34],
  },
  dirt: {
    base: [118, 86, 56],
    light: [160, 124, 84],
    dark: [74, 52, 34],
  },
  stone: {
    base: [116, 122, 130],
    light: [160, 166, 174],
    dark: [74, 80, 90],
  },
  sand: {
    base: [206, 178, 124],
    light: [234, 210, 162],
    dark: [164, 136, 92],
  },
  mud: {
    base: [92, 72, 48],
    light: [126, 102, 70],
    dark: [56, 42, 28],
  },
  water: {
    base: [46, 104, 156],
    light: [92, 160, 206],
    dark: [28, 70, 118],
  },
  ice: {
    base: [176, 210, 224],
    light: [222, 242, 250],
    dark: [132, 172, 194],
  },
};

/**
 * Процедурные бесшовные текстуры террейна.
 *
 * Тайл рисуется один раз на шаблон и живёт в общем кэше рендерера: это
 * небольшие canvas-текстуры, не привязанные к конкретной локации.
 */
export class SceneTerrainPatternService {
  private readonly textures = new Map<SceneTerrainKind, Texture>();

  /** Текстура шаблона; неизвестный шаблон получает траву. */
  texture(kind: string | undefined): Texture {
    const normalized = this.normalize(kind);
    const cached = this.textures.get(normalized);
    if (cached !== undefined) {
      return cached;
    }

    const texture = Texture.from(this.renderTile(normalized));
    texture.source.style.addressMode = 'repeat';
    texture.source.style.update();
    this.textures.set(normalized, texture);

    return texture;
  }

  /**
   * Паттерн поверхности в единицах сцены.
   *
   * Один тайл равен примерно двум ипари (`2 × resolution` единиц), сдвиг
   * детерминирован от id элемента: одинаковые пятна не выстраиваются штампом.
   * У воды тайл мельче — один ипари, чтобы течение читалось вдоль ленты.
   */
  pattern(kind: string | undefined, elementId: number, resolution: number): FillPattern {
    const period = Math.max(1, resolution) * (kind === 'water' ? 1 : 2);
    const texture = this.texture(kind);
    const seed = Math.abs(Math.trunc(elementId)) * 2654435761;
    const offsetX = ((seed % 1024) / 1024) * period;
    const offsetY = (((seed >>> 10) % 1024) / 1024) * period;
    const matrix = new Matrix().scale(period / TEXTURE_SIZE, period / TEXTURE_SIZE).translate(offsetX, offsetY);
    const pattern = new FillPattern({ texture, textureSpace: 'global' });
    pattern.setTransform(matrix);

    return pattern;
  }

  /** Освобождает текстуры. */
  destroy(): void {
    this.textures.forEach((texture) => texture.destroy(true));
    this.textures.clear();
  }

  private normalize(kind: string | undefined): SceneTerrainKind {
    return kind !== undefined && kind in PALETTES ? (kind as SceneTerrainKind) : 'grass';
  }

  /** Рисует бесшовный тайл шаблона. */
  private renderTile(kind: SceneTerrainKind): HTMLCanvasElement {
    const canvas = document.createElement('canvas');
    canvas.width = TEXTURE_SIZE;
    canvas.height = TEXTURE_SIZE;
    const context = canvas.getContext('2d');
    if (context === null) {
      return canvas;
    }

    const palette = PALETTES[kind];
    this.paintBase(context, palette, this.seedOf(kind));
    this.paintDetails(kind, context, palette, this.seedOf(kind) + 97);

    return canvas;
  }

  /** Зерно шаблона: детерминированное и разное для каждого материала. */
  private seedOf(kind: SceneTerrainKind): number {
    return [...kind].reduce((seed, character) => seed * 31 + character.charCodeAt(0), 7) >>> 0;
  }

  /** База: цвет плюс две-три октавы тайлового шума. */
  private paintBase(context: CanvasRenderingContext2D, palette: Palette, seed: number): void {
    const image = context.createImageData(TEXTURE_SIZE, TEXTURE_SIZE);
    const octaves: { cells: number; weight: number; seed: number; noise: Float32Array }[] = [
      { cells: 4, weight: 0.5, seed, noise: new Float32Array(0) },
      { cells: 9, weight: 0.3, seed: seed + 13, noise: new Float32Array(0) },
      { cells: 23, weight: 0.2, seed: seed + 37, noise: new Float32Array(0) },
    ];
    for (const octave of octaves) {
      octave.noise = this.noise(TEXTURE_SIZE, octave.cells, octave.seed);
    }

    for (let y = 0; y < TEXTURE_SIZE; y += 1) {
      for (let x = 0; x < TEXTURE_SIZE; x += 1) {
        const index = y * TEXTURE_SIZE + x;
        let noise = 0;
        for (const octave of octaves) {
          noise += octave.noise[index] * octave.weight;
        }

        const color = noise >= 0 ? palette.light : palette.dark;
        const amount = Math.min(1, Math.abs(noise) * 1.15);
        const pixel = index * 4;
        image.data[pixel] = Math.round(palette.base[0] + (color[0] - palette.base[0]) * amount);
        image.data[pixel + 1] = Math.round(palette.base[1] + (color[1] - palette.base[1]) * amount);
        image.data[pixel + 2] = Math.round(palette.base[2] + (color[2] - palette.base[2]) * amount);
        image.data[pixel + 3] = 255;
      }
    }

    context.putImageData(image, 0, 0);
  }

  /** Тайловый value-noise в диапазоне −1…1. */
  private noise(size: number, cells: number, seed: number): Float32Array {
    const random = this.random(seed);
    const grid: number[] = [];
    for (let index = 0; index < cells * cells; index += 1) {
      grid.push(random() * 2 - 1);
    }

    const result = new Float32Array(size * size);
    for (let y = 0; y < size; y += 1) {
      const gy = (y / size) * cells;
      const y0 = Math.floor(gy);
      const fy = this.smooth(gy - y0);
      for (let x = 0; x < size; x += 1) {
        const gx = (x / size) * cells;
        const x0 = Math.floor(gx);
        const fx = this.smooth(gx - x0);
        const a = grid[(y0 % cells) * cells + (x0 % cells)];
        const b = grid[(y0 % cells) * cells + ((x0 + 1) % cells)];
        const c = grid[((y0 + 1) % cells) * cells + (x0 % cells)];
        const d = grid[((y0 + 1) % cells) * cells + ((x0 + 1) % cells)];
        const top = a + (b - a) * fx;
        const bottom = c + (d - c) * fx;
        result[y * size + x] = top + (bottom - top) * fy;
      }
    }

    return result;
  }

  /** Детали материала поверх шума. */
  private paintDetails(
    kind: SceneTerrainKind,
    context: CanvasRenderingContext2D,
    palette: Palette,
    seed: number,
  ): void {
    const random = this.random(seed);
    switch (kind) {
      case 'grass':
        this.grassBlades(context, palette, random);
        break;
      case 'dirt':
        this.pebbles(context, palette, random, 0.35);
        this.blotches(context, palette.dark, random, 18, 0.12);
        break;
      case 'stone':
        this.cracks(context, palette, random, 16);
        this.pebbles(context, palette, random, 0.18);
        break;
      case 'sand':
        this.ripples(context, palette, random, 24, 0.16);
        this.pebbles(context, palette, random, 0.08);
        break;
      case 'mud':
        this.blotches(context, palette.dark, random, 26, 0.16);
        this.pebbles(context, palette, random, 0.1);
        break;
      case 'water':
        this.ripples(context, palette, random, 30, 0.18);
        break;
      case 'ice':
        this.cracks(context, palette, random, 22, 0.35);
        this.blotches(context, palette.light, random, 12, 0.18);
        break;
    }
  }

  /** Смещения, при которых деталь у края повторяется с другой стороны. */
  private wrappedOffsets(x: number, y: number, extent: number): [number, number][] {
    const xs = [0];
    if (x < extent) xs.push(TEXTURE_SIZE);
    if (x > TEXTURE_SIZE - extent) xs.push(-TEXTURE_SIZE);
    const ys = [0];
    if (y < extent) ys.push(TEXTURE_SIZE);
    if (y > TEXTURE_SIZE - extent) ys.push(-TEXTURE_SIZE);
    const offsets: [number, number][] = [];
    for (const offsetX of xs) {
      for (const offsetY of ys) {
        offsets.push([offsetX, offsetY]);
      }
    }

    return offsets;
  }

  private grassBlades(context: CanvasRenderingContext2D, palette: Palette, random: () => number): void {
    context.lineCap = 'round';
    for (let index = 0; index < 520; index += 1) {
      const x = random() * TEXTURE_SIZE;
      const y = random() * TEXTURE_SIZE;
      const length = 2 + random() * 5;
      const lean = (random() - 0.5) * 4;
      const light = random() > 0.5;
      const color = light ? palette.light : palette.dark;
      const alpha = 0.12 + random() * 0.18;
      const width = 0.5 + random() * 0.6;
      for (const [offsetX, offsetY] of this.wrappedOffsets(x, y, 10)) {
        context.strokeStyle = `rgba(${color[0]}, ${color[1]}, ${color[2]}, ${alpha})`;
        context.lineWidth = width;
        context.beginPath();
        context.moveTo(x + offsetX, y + offsetY);
        context.lineTo(x + offsetX + lean, y + offsetY - length);
        context.stroke();
      }
    }
  }

  private pebbles(context: CanvasRenderingContext2D, palette: Palette, random: () => number, alpha: number): void {
    for (let index = 0; index < 180; index += 1) {
      const x = random() * TEXTURE_SIZE;
      const y = random() * TEXTURE_SIZE;
      const radius = 0.6 + random() * 1.8;
      const color = random() > 0.45 ? palette.light : palette.dark;
      const fill = `rgba(${color[0]}, ${color[1]}, ${color[2]}, ${alpha * (0.5 + random() * 0.5)})`;
      const rotation = random() * Math.PI;
      const squash = 0.6 + random() * 0.6;
      for (const [offsetX, offsetY] of this.wrappedOffsets(x, y, radius + 2)) {
        context.fillStyle = fill;
        context.beginPath();
        context.ellipse(x + offsetX, y + offsetY, radius, radius * squash, rotation, 0, Math.PI * 2);
        context.fill();
      }
    }
  }

  private blotches(
    context: CanvasRenderingContext2D,
    color: [number, number, number],
    random: () => number,
    count: number,
    alpha: number,
  ): void {
    for (let index = 0; index < count; index += 1) {
      const x = random() * TEXTURE_SIZE;
      const y = random() * TEXTURE_SIZE;
      const radius = 8 + random() * 28;
      for (const [offsetX, offsetY] of this.wrappedOffsets(x, y, radius)) {
        const centerX = x + offsetX;
        const centerY = y + offsetY;
        const gradient = context.createRadialGradient(centerX, centerY, 0, centerX, centerY, radius);
        gradient.addColorStop(0, `rgba(${color[0]}, ${color[1]}, ${color[2]}, ${alpha})`);
        gradient.addColorStop(1, `rgba(${color[0]}, ${color[1]}, ${color[2]}, 0)`);
        context.fillStyle = gradient;
        context.fillRect(centerX - radius, centerY - radius, radius * 2, radius * 2);
      }
    }
  }

  private cracks(
    context: CanvasRenderingContext2D,
    palette: Palette,
    random: () => number,
    count: number,
    alpha = 0.5,
  ): void {
    context.lineCap = 'round';
    for (let index = 0; index < count; index += 1) {
      const startX = random() * TEXTURE_SIZE;
      const startY = random() * TEXTURE_SIZE;
      const steps = 3 + Math.floor(random() * 4);
      const points: [number, number][] = [[startX, startY]];
      let x = startX;
      let y = startY;
      for (let step = 0; step < steps; step += 1) {
        x += (random() - 0.5) * 26;
        y += (random() - 0.5) * 26;
        points.push([x, y]);
      }

      context.strokeStyle = `rgba(${palette.dark[0]}, ${palette.dark[1]}, ${palette.dark[2]}, ${alpha * (0.4 + random() * 0.5)})`;
      context.lineWidth = 0.5 + random() * 1.1;
      for (const [offsetX, offsetY] of this.wrappedOffsets(startX, startY, 80)) {
        context.beginPath();
        for (let point = 0; point < points.length; point += 1) {
          const [pointX, pointY] = points[point];
          if (point === 0) {
            context.moveTo(pointX + offsetX, pointY + offsetY);
          } else {
            context.lineTo(pointX + offsetX, pointY + offsetY);
          }
        }

        context.stroke();
      }
    }
  }

  private ripples(
    context: CanvasRenderingContext2D,
    palette: Palette,
    random: () => number,
    count: number,
    alpha = 0.35,
  ): void {
    context.lineCap = 'round';
    for (let index = 0; index < count; index += 1) {
      const x = random() * TEXTURE_SIZE;
      const y = random() * TEXTURE_SIZE;
      const length = 10 + random() * 30;
      const bend = (random() - 0.5) * 8;
      const color = random() > 0.4 ? palette.light : palette.dark;
      context.strokeStyle = `rgba(${color[0]}, ${color[1]}, ${color[2]}, ${alpha * (0.5 + random() * 0.5)})`;
      context.lineWidth = 0.7 + random() * 1.2;
      for (const [offsetX, offsetY] of this.wrappedOffsets(x, y, length + 6)) {
        context.beginPath();
        context.moveTo(x + offsetX, y + offsetY);
        context.quadraticCurveTo(x + offsetX + length / 2, y + offsetY + bend, x + offsetX + length, y + offsetY);
        context.stroke();
      }
    }
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

  private smooth(value: number): number {
    return value * value * (3 - 2 * value);
  }
}
