import type { MapDecorSymbol } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import { MAP_DECOR_SYMBOL_SIZE } from '@/modules/Roleplay/Map/Constant/Map/MAP_DECOR';
import type { MapDecorPoint } from '@/modules/Roleplay/Map/Service/MapDecorService';
import { mixHex, withAlpha } from '@/modules/Roleplay/Map/Utils/color';

const TAU = Math.PI * 2;

/** Порядок отрисовки: трава под деревьями. */
const DRAW_ORDER: MapDecorSymbol[] = ['field', 'grass', 'pine', 'tree'];

/** Всё, что нужно painter'у для одного прохода декора. */
export interface MapDecorPaintOptions {
  points: MapDecorPoint[];
  /** Цвет области: символы выводятся из него. */
  color: string;
  /** Непрозрачность декора, 0–1. */
  alpha: number;
  /** Мировой размер, ниже которого символ не опускается: ~3 экранных px. */
  minSize: number;
}

/** Рисует рассыпку декора в статичном слое карты. */
export class MapDecorPainter {
  /** Точка входа: группирует точки по символам и рисует их пачками. */
  paint(context: CanvasRenderingContext2D, options: MapDecorPaintOptions): void {
    if (options.points.length === 0 || options.alpha <= 0) return;

    context.save();
    context.globalAlpha = 1;
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

    for (const kind of DRAW_ORDER) {
      const group = groups.get(kind);
      if (!group) continue;

      const layer = { ...options, points: group };
      if (kind === 'grass') {
        this.grass(context, layer);
      } else if (kind === 'pine') {
        this.pine(context, layer);
      } else if (kind === 'field') {
        this.field(context, layer);
      } else {
        this.tree(context, layer);
      }
    }

    context.restore();
  }

  private grass(context: CanvasRenderingContext2D, options: MapDecorPaintOptions): void {
    const tones = [0.38, 0.52, 0.66];
    const width = Math.max(0.45, this.unit(options, options.points[0]) * 0.085);
    tones.forEach((depth, tone) => {
      context.strokeStyle = withAlpha(mixHex(options.color, '#1d2c18', depth), options.alpha);
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

  private tree(context: CanvasRenderingContext2D, options: MapDecorPaintOptions): void {
    const trunk = withAlpha(mixHex('#7a5a3c', '#2c211a', 0.45), options.alpha * 0.7);

    context.strokeStyle = trunk;
    context.lineWidth = Math.max(0.5, this.unit(options, options.points[0]) * 0.075);
    context.beginPath();
    for (const point of options.points) {
      const unit = this.unit(options, point);
      context.moveTo(point.x, point.y);
      context.lineTo(point.x, point.y - unit * 0.45);
    }
    context.stroke();

    // Четыре оттенка кроны: разброс по светлоте читается как объём и ломает
    // одинаковость кружков, но остаётся батчем — четыре заливки на область.
    const tones = [0.28, 0.42, 0.56, 0.7];
    tones.forEach((depth, tone) => {
      context.fillStyle = withAlpha(mixHex(options.color, '#16260f', depth), options.alpha);
      context.beginPath();
      for (const point of options.points) {
        if (point.variant % tones.length !== tone) continue;
        const unit = this.unit(options, point);
        const top = point.y - unit * 0.55;
        const radius = unit * 0.3;
        circlePath(context, point.x, top, radius);
        circlePath(context, point.x - radius * 0.85, top + radius * 0.6, radius * 0.72);
        circlePath(context, point.x + radius * 0.85, top + radius * 0.55, radius * 0.7);
        if (point.variant % 2 === 0) {
          circlePath(context, point.x + radius * 0.15, top - radius * 0.55, radius * 0.55);
        }
      }
      context.fill();
    });
  }

  private pine(context: CanvasRenderingContext2D, options: MapDecorPaintOptions): void {
    const trunk = withAlpha(mixHex('#7a5a3c', '#2c211a', 0.45), options.alpha * 0.7);

    context.strokeStyle = trunk;
    context.lineWidth = Math.max(0.5, this.unit(options, options.points[0]) * 0.07);
    context.beginPath();
    for (const point of options.points) {
      const unit = this.unit(options, point);
      context.moveTo(point.x, point.y);
      context.lineTo(point.x, point.y - unit * 0.24);
    }
    context.stroke();

    const tones = [0.3, 0.44, 0.58, 0.72];
    tones.forEach((depth, tone) => {
      context.fillStyle = withAlpha(mixHex(options.color, '#0f2412', depth), options.alpha);
      context.beginPath();
      for (const point of options.points) {
        if (point.variant % tones.length !== tone) continue;
        const unit = this.unit(options, point);
        const tiers = point.variant % 2 === 0 ? 3 : 4;
        for (let tier = 0; tier < tiers; tier += 1) {
          const baseY = point.y - unit * (0.16 + tier * 0.19);
          const tipY = baseY - unit * 0.34;
          const half = unit * (0.3 - tier * 0.045);
          context.moveTo(point.x - half, baseY);
          context.lineTo(point.x, tipY);
          context.lineTo(point.x + half, baseY);
          context.closePath();
        }
      }
      context.fill();
    });
  }

  private field(context: CanvasRenderingContext2D, options: MapDecorPaintOptions): void {
    const tones = [0.4, 0.55];
    tones.forEach((depth, tone) => {
      context.strokeStyle = withAlpha(mixHex(options.color, '#3a3413', depth), options.alpha);
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

function circlePath(context: CanvasRenderingContext2D, x: number, y: number, radius: number): void {
  context.moveTo(x + radius, y);
  context.arc(x, y, radius, 0, TAU);
}

function rotate(x: number, y: number, angle: number): [number, number] {
  const cos = Math.cos(angle);
  const sin = Math.sin(angle);

  return [x * cos - y * sin, x * sin + y * cos];
}
