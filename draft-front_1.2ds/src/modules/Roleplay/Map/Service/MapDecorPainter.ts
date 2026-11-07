import type { MapDecorSymbol } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import { MAP_DECOR_SYMBOL_SIZE } from '@/modules/Roleplay/Map/Constant/Map/MAP_DECOR';
import type { MapDecorPoint } from '@/modules/Roleplay/Map/Service/MapDecorService';
import { mixHex } from '@/modules/Roleplay/Map/Utils/color';

const TAU = Math.PI * 2;

/** Трава и жнивьё — плоские символы, рисуются пачками под деревьями. */
const FLAT_ORDER: MapDecorSymbol[] = ['field', 'grass'];

const TREE_TRUNK = mixHex('#7a5a3c', '#2c211a', 0.45);
const TREE_DEPTHS = [0.28, 0.42, 0.56, 0.7];
const PINE_DEPTHS = [0.3, 0.44, 0.58, 0.72];

/**
 * До этого числа деревьев порядок отрисовки точный — по одному. Выше — полосы
 * по глубине: плотный лес иначе перерисовывается слишком долго при панораме.
 */
const EXACT_TREE_LIMIT = 160;

/** Высота полосы глубины в размерах символа: в ней деревья уже почти в ряд. */
const BAND_FACTOR = 1.3;

/** Всё, что нужно painter'у для одного прохода декора. */
export interface MapDecorPaintOptions {
  points: MapDecorPoint[];
  /** Цвет области: символы выводятся из него. */
  color: string;
  /** Мировой размер, ниже которого символ не опускается: ~3 экранных px. */
  minSize: number;
}

/** Рисует рассыпку декора в статичном слое карты. */
export class MapDecorPainter {
  /** Точка входа: плоские символы пачками, деревья по одному от дальних к ближним. */
  paint(context: CanvasRenderingContext2D, options: MapDecorPaintOptions): void {
    if (options.points.length === 0) return;

    context.save();
    context.lineCap = 'round';
    context.lineJoin = 'round';

    const groups = new Map<MapDecorSymbol, MapDecorPoint[]>();
    for (const point of options.points) {
      const group = groups.get(point.kind);
      if (group) {
        group.push(point);
      } else {
        groups.set(point.kind, [point]);
      }
    }

    for (const kind of FLAT_ORDER) {
      const group = groups.get(kind);
      if (!group) continue;

      const layer = { ...options, points: group };
      if (kind === 'grass') {
        this.grass(context, layer);
      } else {
        this.field(context, layer);
      }
    }

    // Кроны и ели — единый ряд по глубине: больший `y` ближе к зрителю и
    // рисуется позже, поэтому ближнее дерево перекрывает дальнее, а не наоборот.
    const treeTones = TREE_DEPTHS.map((depth) => mixHex(options.color, '#16260f', depth));
    const pineTones = PINE_DEPTHS.map((depth) => mixHex(options.color, '#0f2412', depth));
    const trees = [...(groups.get('pine') ?? []), ...(groups.get('tree') ?? [])].sort(
      (left, right) => left.y - right.y,
    );
    if (trees.length <= EXACT_TREE_LIMIT) {
      for (const point of trees) {
        const layer = { ...options, points: [point] };
        if (point.kind === 'pine') {
          this.pine(context, layer, pineTones);
        } else {
          this.tree(context, layer, treeTones);
        }
      }
    } else {
      this.bandedTrees(context, options, trees, treeTones, pineTones);
    }

    context.restore();
  }

  /**
   * Плотный лес: полосы по глубине, внутри полосы — пачки по тону.
   * Деревья одной полосы почти на одной глубине, поэтому порядок между ними
   * почти не читается, зато отрисовки на порядок меньше.
   */
  private bandedTrees(
    context: CanvasRenderingContext2D,
    options: MapDecorPaintOptions,
    points: MapDecorPoint[],
    treeTones: string[],
    pineTones: string[],
  ): void {
    let tallest = 0;
    for (const point of points) tallest = Math.max(tallest, this.unit(options, point));
    const height = Math.max(24, tallest * BAND_FACTOR);

    let start = 0;
    let band = 0;
    while (start < points.length) {
      const limit = points[start].y + height;
      let end = start;
      while (end < points.length && points[end].y <= limit) end += 1;
      this.treeBand(context, options, points.slice(start, end), band, treeTones, pineTones);
      start = end;
      band += 1;
    }
  }

  /** Одна полоса: стволы одной пачкой, кроны и ели — по пачке на оттенок. */
  private treeBand(
    context: CanvasRenderingContext2D,
    options: MapDecorPaintOptions,
    band: MapDecorPoint[],
    bandIndex: number,
    treeTones: string[],
    pineTones: string[],
  ): void {
    context.strokeStyle = TREE_TRUNK;
    context.lineWidth = Math.max(0.5, this.unit(options, band[0]) * 0.07);
    context.beginPath();
    for (const point of band) {
      const unit = this.unit(options, point);
      context.moveTo(point.x, point.y);
      context.lineTo(point.x, point.y - unit * (point.kind === 'pine' ? 0.24 : 0.45));
    }
    context.stroke();

    for (let step = 0; step < pineTones.length; step += 1) {
      const tone = (step + bandIndex) % pineTones.length;
      context.fillStyle = pineTones[tone];
      context.beginPath();
      for (const point of band) {
        if (point.kind !== 'pine' || point.variant % pineTones.length !== tone) continue;
        pinePath(context, point.x, point.y, this.unit(options, point), point.variant);
      }
      context.fill();
    }

    for (let step = 0; step < treeTones.length; step += 1) {
      const tone = (step + bandIndex) % treeTones.length;
      context.fillStyle = treeTones[tone];
      context.beginPath();
      for (const point of band) {
        if (point.kind !== 'tree' || point.variant % treeTones.length !== tone) continue;
        treePath(context, point.x, point.y, this.unit(options, point), point.variant);
      }
      context.fill();
    }
  }

  private grass(context: CanvasRenderingContext2D, options: MapDecorPaintOptions): void {
    const tones = [0.38, 0.52, 0.66];
    const width = Math.max(0.45, this.unit(options, options.points[0]) * 0.085);
    tones.forEach((depth, tone) => {
      context.strokeStyle = mixHex(options.color, '#1d2c18', depth);
      context.lineWidth = width;
      context.beginPath();
      for (const point of options.points) {
        if (point.variant % tones.length !== tone) continue;
        const unit = this.unit(options, point);
        const blades = point.variant % 2 === 0 ? 3 : 4;
        for (let blade = 0; blade < blades; blade += 1) {
          const spread = (blade / Math.max(1, blades - 1) - 0.5) * 1.15 + point.angle;
          const length = unit * (0.5 + ((point.variant + blade) % 3) * 0.18);
          context.moveTo(point.x + Math.sin(spread) * unit * 0.08, point.y);
          context.lineTo(point.x + Math.sin(spread) * length, point.y - Math.cos(spread) * length);
        }
      }
      context.stroke();
    });
  }

  private tree(context: CanvasRenderingContext2D, options: MapDecorPaintOptions, tones: string[]): void {
    const point = options.points[0];
    const unit = this.unit(options, point);

    context.strokeStyle = TREE_TRUNK;
    context.lineWidth = Math.max(0.5, unit * 0.075);
    context.beginPath();
    context.moveTo(point.x, point.y);
    context.lineTo(point.x, point.y - unit * 0.45);
    context.stroke();

    context.fillStyle = tones[point.variant % tones.length];
    context.beginPath();
    treePath(context, point.x, point.y, unit, point.variant);
    context.fill();
  }

  private pine(context: CanvasRenderingContext2D, options: MapDecorPaintOptions, tones: string[]): void {
    const point = options.points[0];
    const unit = this.unit(options, point);

    context.strokeStyle = TREE_TRUNK;
    context.lineWidth = Math.max(0.5, unit * 0.07);
    context.beginPath();
    context.moveTo(point.x, point.y);
    context.lineTo(point.x, point.y - unit * 0.24);
    context.stroke();

    context.fillStyle = tones[point.variant % tones.length];
    context.beginPath();
    pinePath(context, point.x, point.y, unit, point.variant);
    context.fill();
  }

  private field(context: CanvasRenderingContext2D, options: MapDecorPaintOptions): void {
    const tones = [0.4, 0.55];
    tones.forEach((depth, tone) => {
      context.strokeStyle = mixHex(options.color, '#3a3413', depth);
      context.lineWidth = Math.max(0.4, this.unit(options, options.points[0]) * 0.07);
      context.beginPath();
      for (const point of options.points) {
        if (point.variant % tones.length !== tone) continue;
        const unit = this.unit(options, point);
        for (let row = 0; row < 2; row += 1) {
          const offsetY = row * unit * 0.34;
          const half = unit * 0.38;
          const angle = point.angle * 0.4;
          const start = rotate(-half, 0, angle);
          const end = rotate(half, 0, angle);
          context.moveTo(point.x + start[0], point.y + offsetY + start[1]);
          context.lineTo(point.x + end[0], point.y + offsetY + end[1]);
        }
      }
      context.stroke();
    });
  }

  private unit(options: MapDecorPaintOptions, point: MapDecorPoint): number {
    const base = Math.max(MAP_DECOR_SYMBOL_SIZE[point.kind], options.minSize);

    return base * point.size;
  }
}

/** Крона: три-четыре круга, собранные в один путь. */
function treePath(context: CanvasRenderingContext2D, x: number, y: number, unit: number, variant: number): void {
  const radius = unit * 0.3;
  const top = y - unit * 0.55;
  circlePath(context, x, top, radius);
  circlePath(context, x - radius * 0.85, top + radius * 0.6, radius * 0.72);
  circlePath(context, x + radius * 0.85, top + radius * 0.55, radius * 0.7);
  if (variant % 2 === 0) {
    circlePath(context, x + radius * 0.15, top - radius * 0.55, radius * 0.55);
  }
}

/** Ель: три-четыре яруса треугольников. */
function pinePath(context: CanvasRenderingContext2D, x: number, y: number, unit: number, variant: number): void {
  const tiers = variant % 2 === 0 ? 3 : 4;
  for (let tier = 0; tier < tiers; tier += 1) {
    const baseY = y - unit * (0.16 + tier * 0.19);
    const tipY = baseY - unit * 0.34;
    const half = unit * (0.3 - tier * 0.045);
    context.moveTo(x - half, baseY);
    context.lineTo(x, tipY);
    context.lineTo(x + half, baseY);
    context.closePath();
  }
}

function circlePath(context: CanvasRenderingContext2D, x: number, y: number, radius: number): void {
  context.moveTo(x + radius, y);
  context.arc(x, y, radius, 0, TAU);
}

function rotate(x: number, y: number, angle: number): [number, number] {
  const cos = Math.cos(angle);
  const sin = Math.sin(angle);

  return [x * cos - y * sin, x * sin + y * cos];
}
