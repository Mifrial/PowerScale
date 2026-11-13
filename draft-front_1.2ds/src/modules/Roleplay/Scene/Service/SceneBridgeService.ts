import { Graphics } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';

/** Размеры настила, общие для моста и съезда. */
interface DeckMetrics {
  x: number;
  y: number;
  w: number;
  h: number;
  horizontal: boolean;
  short: number;
  base: number;
  line: number;
  rise: number;
  thickness: number;
  rail: number;
  inset: number;
  planks: number;
  /** Съезд идёт от начала прямоугольника к концу; `true` — наоборот. */
  flip: boolean;
}

/**
 * Мост и съезд: настил, доски, перила, сваи и боковая грань.
 *
 * Рисуется в мировых координатах прямоугольника элемента и живёт в слое
 * `ground` над водой, но под акторами: это пол, а не препятствие. Подъём
 * (`props.elevation`) и толщина (`props.height`) дают боковую грань, сваи и
 * тень, поэтому мост читается объёмным. Толщина линий переводится из
 * экранных пикселей в единицы сцены через зум.
 */
export class SceneBridgeService {
  /** Рисует мост или съезд; `null` — элемент не прямоугольный. */
  paint(element: SceneElement, resolution: number, zoom: number): Graphics | null {
    if (element.shape.type !== 'rect') {
      return null;
    }

    const metrics = this.metrics(element, resolution, zoom);

    return element.props.kind === 'ramp' ? this.paintRamp(metrics) : this.paintBridge(metrics);
  }

  /** Мост: ровный настил с перилами и объёмом. */
  private paintBridge(metrics: DeckMetrics): Graphics {
    const graphics = new Graphics();
    this.paintShadow(graphics, metrics);
    this.paintSide(graphics, metrics);
    this.paintDeck(graphics, metrics);
    this.paintEndCaps(graphics, metrics);
    this.paintRails(graphics, metrics);

    return graphics;
  }

  /** Съезд: наклонный настил со ступенями и сходящей на нет боковой гранью. */
  private paintRamp(metrics: DeckMetrics): Graphics {
    const graphics = new Graphics();
    this.paintShadow(graphics, metrics);
    this.paintRampSide(graphics, metrics);
    this.paintDeck(graphics, metrics, true);
    this.paintRampCleats(graphics, metrics);
    this.paintRampEnds(graphics, metrics);
    this.paintRails(graphics, metrics);

    return graphics;
  }

  /** Мягкая тень на воде: несколько слоёв, смещение растёт с подъёмом. */
  private paintShadow(graphics: Graphics, metrics: DeckMetrics): void {
    if (metrics.rise <= 0) {
      return;
    }

    const spread = metrics.rise * 0.18;
    for (let layer = 3; layer >= 1; layer -= 1) {
      const grow = spread * layer;
      graphics
        .roundRect(
          metrics.x - grow + spread * 0.6,
          metrics.y - grow + spread,
          metrics.w + grow * 2,
          metrics.h + grow * 2,
          Math.max(1, metrics.short * 0.08),
        )
        .fill({ color: SCENE_BRIDGE.shadowColor, alpha: SCENE_BRIDGE.shadowAlpha / (layer + 1) });
    }
  }

  /** Боковая грань моста: виден торец настила и сваи. */
  private paintSide(graphics: Graphics, metrics: DeckMetrics): void {
    if (metrics.rise <= 0) {
      return;
    }

    const pile = Math.max(2, metrics.short * SCENE_BRIDGE.pileWidth);
    const piles = Math.max(2, Math.round(metrics.planks / 4));
    if (metrics.horizontal) {
      const top = metrics.y + metrics.h;
      graphics.rect(metrics.x, top, metrics.w, metrics.rise).fill({ color: SCENE_BRIDGE.sideColor });
      for (let index = 1; index < metrics.planks; index += 1) {
        const px = metrics.x + (metrics.w * index) / metrics.planks;
        graphics.moveTo(px, top).lineTo(px, top + metrics.rise);
      }
      graphics.stroke({ color: SCENE_BRIDGE.sideDarkColor, width: metrics.line, alpha: 0.5 });
      graphics
        .rect(metrics.x, top + metrics.rise - metrics.thickness, metrics.w, metrics.thickness)
        .fill({ color: SCENE_BRIDGE.sideDarkColor, alpha: 0.95 });
      for (let index = 0; index <= piles; index += 1) {
        const px = metrics.x + (metrics.w * index) / piles;
        graphics.rect(px - pile / 2, top, pile, metrics.rise).fill({ color: SCENE_BRIDGE.pileColor, alpha: 0.85 });
        graphics
          .ellipse(px, top + metrics.rise, pile * 1.6, pile * 0.45)
          .stroke({ color: 0xdbeafe, width: metrics.line, alpha: 0.16 });
      }
    } else {
      const left = metrics.x + metrics.w;
      graphics.rect(left, metrics.y, metrics.rise, metrics.h).fill({ color: SCENE_BRIDGE.sideColor });
      for (let index = 1; index < metrics.planks; index += 1) {
        const py = metrics.y + (metrics.h * index) / metrics.planks;
        graphics.moveTo(left, py).lineTo(left + metrics.rise, py);
      }
      graphics.stroke({ color: SCENE_BRIDGE.sideDarkColor, width: metrics.line, alpha: 0.5 });
      graphics
        .rect(left + metrics.rise - metrics.thickness, metrics.y, metrics.thickness, metrics.h)
        .fill({ color: SCENE_BRIDGE.sideDarkColor, alpha: 0.95 });
      for (let index = 0; index <= piles; index += 1) {
        const py = metrics.y + (metrics.h * index) / piles;
        graphics.rect(left, py - pile / 2, metrics.rise, pile).fill({ color: SCENE_BRIDGE.pileColor, alpha: 0.85 });
        graphics
          .ellipse(left + metrics.rise, py, pile * 0.45, pile * 1.6)
          .stroke({ color: 0xdbeafe, width: metrics.line, alpha: 0.16 });
      }
    }
  }

  /** Наклонная боковая грань съезда: высота растёт от низкого конца. */
  private paintRampSide(graphics: Graphics, metrics: DeckMetrics): void {
    if (metrics.rise <= 0) {
      return;
    }

    if (metrics.horizontal) {
      const bottom = metrics.y + metrics.h;
      const points = metrics.flip
        ? [metrics.x, bottom, metrics.x + metrics.w, bottom, metrics.x, bottom + metrics.rise]
        : [metrics.x, bottom, metrics.x + metrics.w, bottom, metrics.x + metrics.w, bottom + metrics.rise];
      graphics.poly(points).fill({ color: SCENE_BRIDGE.sideColor });
      const seamFrom = metrics.flip ? metrics.x : metrics.x + metrics.w;
      for (let index = 1; index < metrics.planks; index += 1) {
        const px = metrics.x + (metrics.w * index) / metrics.planks;
        const height = (metrics.rise * (metrics.flip ? metrics.x + metrics.w - px : px - metrics.x)) / metrics.w;
        graphics.moveTo(px, bottom).lineTo(px, bottom + height);
      }
      graphics.stroke({ color: SCENE_BRIDGE.sideDarkColor, width: metrics.line, alpha: 0.5 });
      graphics
        .moveTo(seamFrom, bottom)
        .lineTo(seamFrom, bottom + metrics.rise)
        .stroke({ color: SCENE_BRIDGE.sideDarkColor, width: metrics.line * 1.5, alpha: 0.9 });
    } else {
      const right = metrics.x + metrics.w;
      const points = metrics.flip
        ? [right, metrics.y, right, metrics.y + metrics.h, right + metrics.rise, metrics.y]
        : [right, metrics.y, right, metrics.y + metrics.h, right + metrics.rise, metrics.y + metrics.h];
      graphics.poly(points).fill({ color: SCENE_BRIDGE.sideColor });
      const seamFrom = metrics.flip ? metrics.y : metrics.y + metrics.h;
      for (let index = 1; index < metrics.planks; index += 1) {
        const py = metrics.y + (metrics.h * index) / metrics.planks;
        const width = (metrics.rise * (metrics.flip ? metrics.y + metrics.h - py : py - metrics.y)) / metrics.h;
        graphics.moveTo(right, py).lineTo(right + width, py);
      }
      graphics.stroke({ color: SCENE_BRIDGE.sideDarkColor, width: metrics.line, alpha: 0.5 });
      graphics
        .moveTo(right, seamFrom)
        .lineTo(right + metrics.rise, seamFrom)
        .stroke({ color: SCENE_BRIDGE.sideDarkColor, width: metrics.line * 1.5, alpha: 0.9 });
    }
  }

  /** Настил: тёмная кромка, доски с чередованием и швы. */
  private paintDeck(graphics: Graphics, metrics: DeckMetrics, ramp = false): void {
    graphics.rect(metrics.x, metrics.y, metrics.w, metrics.h).fill({ color: SCENE_BRIDGE.edgeColor });
    graphics
      .rect(
        metrics.x + metrics.inset,
        metrics.y + metrics.inset,
        Math.max(1, metrics.w - metrics.inset * 2),
        Math.max(1, metrics.h - metrics.inset * 2),
      )
      .fill({ color: SCENE_BRIDGE.deckColor });

    const gap = Math.max(1, metrics.line * 0.6);
    for (let index = 0; index < metrics.planks; index += 1) {
      const tone = this.plankTone(index);
      if (metrics.horizontal) {
        const from = metrics.x + (metrics.w * index) / metrics.planks + gap / 2;
        const to = metrics.x + (metrics.w * (index + 1)) / metrics.planks - gap / 2;
        graphics
          .rect(from, metrics.y + metrics.inset, Math.max(1, to - from), Math.max(1, metrics.h - metrics.inset * 2))
          .fill({ color: tone });
        if (index % 3 === 0) {
          const grain = (from + to) / 2;
          graphics
            .moveTo(grain, metrics.y + metrics.inset + metrics.line)
            .lineTo(grain, metrics.y + metrics.h - metrics.inset - metrics.line)
            .stroke({ color: SCENE_BRIDGE.plankColor, width: metrics.line * 0.6, alpha: 0.3 });
        }
        graphics
          .moveTo(metrics.x + (metrics.w * (index + 1)) / metrics.planks, metrics.y + metrics.inset)
          .lineTo(metrics.x + (metrics.w * (index + 1)) / metrics.planks, metrics.y + metrics.h - metrics.inset)
          .stroke({ color: SCENE_BRIDGE.edgeColor, width: metrics.line, alpha: ramp ? 0.75 : 0.45 });
      } else {
        const from = metrics.y + (metrics.h * index) / metrics.planks + gap / 2;
        const to = metrics.y + (metrics.h * (index + 1)) / metrics.planks - gap / 2;
        graphics
          .rect(metrics.x + metrics.inset, from, Math.max(1, metrics.w - metrics.inset * 2), Math.max(1, to - from))
          .fill({ color: tone });
        if (index % 3 === 0) {
          const grain = (from + to) / 2;
          graphics
            .moveTo(metrics.x + metrics.inset + metrics.line, grain)
            .lineTo(metrics.x + metrics.w - metrics.inset - metrics.line, grain)
            .stroke({ color: SCENE_BRIDGE.plankColor, width: metrics.line * 0.6, alpha: 0.3 });
        }
        graphics
          .moveTo(metrics.x + metrics.inset, metrics.y + (metrics.h * (index + 1)) / metrics.planks)
          .lineTo(metrics.x + metrics.w - metrics.inset, metrics.y + (metrics.h * (index + 1)) / metrics.planks)
          .stroke({ color: SCENE_BRIDGE.edgeColor, width: metrics.line, alpha: ramp ? 0.75 : 0.45 });
      }
    }
  }

  /** Торцевые балки: настил не обрывается доской. */
  private paintEndCaps(graphics: Graphics, metrics: DeckMetrics): void {
    const cap = Math.max(2, metrics.short * SCENE_BRIDGE.endCapWidth);
    if (metrics.horizontal) {
      graphics.rect(metrics.x, metrics.y, cap, metrics.h).fill({ color: SCENE_BRIDGE.edgeColor, alpha: 0.92 });
      graphics
        .rect(metrics.x + metrics.w - cap, metrics.y, cap, metrics.h)
        .fill({ color: SCENE_BRIDGE.edgeColor, alpha: 0.92 });
    } else {
      graphics.rect(metrics.x, metrics.y, metrics.w, cap).fill({ color: SCENE_BRIDGE.edgeColor, alpha: 0.92 });
      graphics
        .rect(metrics.x, metrics.y + metrics.h - cap, metrics.w, cap)
        .fill({ color: SCENE_BRIDGE.edgeColor, alpha: 0.92 });
    }
  }

  /** Поперечные ступени съезда: читаются как наклонные доски. */
  private paintRampCleats(graphics: Graphics, metrics: DeckMetrics): void {
    const cleat = Math.max(1, metrics.line);
    for (let index = 1; index < metrics.planks; index += 1) {
      if (metrics.horizontal) {
        const px = metrics.x + (metrics.w * index) / metrics.planks;
        graphics
          .moveTo(px, metrics.y + metrics.inset)
          .lineTo(px, metrics.y + metrics.h - metrics.inset)
          .stroke({ color: SCENE_BRIDGE.edgeColor, width: cleat, alpha: 0.6 });
      } else {
        const py = metrics.y + (metrics.h * index) / metrics.planks;
        graphics
          .moveTo(metrics.x + metrics.inset, py)
          .lineTo(metrics.x + metrics.w - metrics.inset, py)
          .stroke({ color: SCENE_BRIDGE.edgeColor, width: cleat, alpha: 0.6 });
      }
    }
  }

  /** Порог съезда: низкий конец светлее, высокий — тёмная балка. */
  private paintRampEnds(graphics: Graphics, metrics: DeckMetrics): void {
    const cap = Math.max(2, metrics.short * SCENE_BRIDGE.endCapWidth);
    const low = metrics.flip ? 1 : 0;
    const high = 1 - low;
    if (metrics.horizontal) {
      const lowX = low === 0 ? metrics.x : metrics.x + metrics.w - cap;
      const highX = high === 0 ? metrics.x : metrics.x + metrics.w - cap;
      graphics.rect(lowX, metrics.y, cap, metrics.h).fill({ color: SCENE_BRIDGE.railHighlightColor, alpha: 0.35 });
      graphics.rect(highX, metrics.y, cap, metrics.h).fill({ color: SCENE_BRIDGE.edgeColor, alpha: 0.95 });
    } else {
      const lowY = low === 0 ? metrics.y : metrics.y + metrics.h - cap;
      const highY = high === 0 ? metrics.y : metrics.y + metrics.h - cap;
      graphics.rect(metrics.x, lowY, metrics.w, cap).fill({ color: SCENE_BRIDGE.railHighlightColor, alpha: 0.35 });
      graphics.rect(metrics.x, highY, metrics.w, cap).fill({ color: SCENE_BRIDGE.edgeColor, alpha: 0.95 });
    }
  }

  /** Перила по длинным сторонам и столбики с бликом. */
  private paintRails(graphics: Graphics, metrics: DeckMetrics): void {
    if (metrics.horizontal) {
      graphics.rect(metrics.x, metrics.y, metrics.w, metrics.rail).fill({ color: SCENE_BRIDGE.railColor });
      graphics
        .rect(metrics.x, metrics.y + metrics.h - metrics.rail, metrics.w, metrics.rail)
        .fill({ color: SCENE_BRIDGE.railColor });
      graphics
        .rect(metrics.x, metrics.y, metrics.w, metrics.line)
        .fill({ color: SCENE_BRIDGE.railHighlightColor, alpha: 0.55 });
      graphics
        .rect(metrics.x, metrics.y + metrics.h - metrics.rail, metrics.w, metrics.line)
        .fill({ color: SCENE_BRIDGE.railHighlightColor, alpha: 0.35 });
    } else {
      graphics.rect(metrics.x, metrics.y, metrics.rail, metrics.h).fill({ color: SCENE_BRIDGE.railColor });
      graphics
        .rect(metrics.x + metrics.w - metrics.rail, metrics.y, metrics.rail, metrics.h)
        .fill({ color: SCENE_BRIDGE.railColor });
      graphics
        .rect(metrics.x, metrics.y, metrics.line, metrics.h)
        .fill({ color: SCENE_BRIDGE.railHighlightColor, alpha: 0.55 });
      graphics
        .rect(metrics.x + metrics.w - metrics.rail, metrics.y, metrics.line, metrics.h)
        .fill({ color: SCENE_BRIDGE.railHighlightColor, alpha: 0.35 });
    }

    this.paintPosts(graphics, metrics);
  }

  /** Столбики перил: тень, тело и светлая крышка. */
  private paintPosts(graphics: Graphics, metrics: DeckMetrics): void {
    const posts = Math.max(2, Math.round(metrics.planks / SCENE_BRIDGE.postEveryPlanks));
    const width = Math.max(2, metrics.rail * 0.5);
    const height = Math.max(2, metrics.short * SCENE_BRIDGE.postHeight * 0.65);
    for (let index = 0; index <= posts; index += 1) {
      const t = index / posts;
      if (metrics.horizontal) {
        const px = metrics.x + metrics.w * t;
        this.paintPost(graphics, px, metrics.y + metrics.rail / 2, width, height, metrics.line);
        this.paintPost(graphics, px, metrics.y + metrics.h - metrics.rail / 2, width, height, metrics.line);
      } else {
        const py = metrics.y + metrics.h * t;
        this.paintPost(graphics, metrics.x + metrics.rail / 2, py, width, height, metrics.line);
        this.paintPost(graphics, metrics.x + metrics.w - metrics.rail / 2, py, width, height, metrics.line);
      }
    }
  }

  /** Один столбик. */
  private paintPost(graphics: Graphics, x: number, y: number, width: number, height: number, line: number): void {
    graphics
      .rect(x - width / 2 + line * 0.5, y - height / 2 + line * 0.5, width, height)
      .fill({ color: SCENE_BRIDGE.shadowColor, alpha: 0.35 });
    graphics.rect(x - width / 2, y - height / 2, width, height).fill({ color: SCENE_BRIDGE.postColor });
    graphics
      .rect(x - width / 2, y - height / 2, width, Math.max(1, line * 0.8))
      .fill({ color: SCENE_BRIDGE.postCapColor, alpha: 0.9 });
  }

  /** Чередование досок. */
  private plankTone(index: number): number {
    const tones = [
      SCENE_BRIDGE.deckColor,
      SCENE_BRIDGE.deckLightColor,
      SCENE_BRIDGE.deckColor,
      SCENE_BRIDGE.deckDarkColor,
    ];

    return tones[index % tones.length];
  }

  /** Размеры настила из прямоугольника и свойств элемента. */
  private metrics(element: SceneElement, resolution: number, zoom: number): DeckMetrics {
    if (element.shape.type !== 'rect') {
      throw new Error('SceneBridgeService.metrics требует прямоугольник');
    }

    const base = Math.max(1, resolution);
    const shape = element.shape;
    const short = Math.max(1, Math.min(shape.w, shape.h));
    const elevation = Math.max(0, element.props.elevation ?? 0);
    const height = Math.max(0, element.props.height ?? 0);
    // Старые мосты хранят высоту над водой в `height`, а `elevation` у них 0.
    // Новый пресет задаёт оба: подъём — `elevation`, толщину — `height`.
    // Оба нуля — плоский настил на земле.
    const rise = elevation > 0 ? elevation : height;
    const thickness = Math.min(
      Math.max(1, height > 0 ? height : base * SCENE_BRIDGE.thicknessIpari),
      Math.max(1, rise * SCENE_BRIDGE.thicknessShare),
    );
    const rotation = (((element.props.rotation ?? 0) % 360) + 360) % 360;

    return {
      x: shape.x,
      y: shape.y,
      w: shape.w,
      h: shape.h,
      horizontal: shape.w >= shape.h,
      short,
      base,
      line: Math.max(1, 1.5 / Math.max(0.001, zoom)),
      rise,
      thickness,
      rail: Math.max(1, short * SCENE_BRIDGE.railWidth),
      inset: Math.max(1, short * SCENE_BRIDGE.deckInset),
      planks: Math.max(1, Math.round((shape.w >= shape.h ? shape.w : shape.h) / (base * SCENE_BRIDGE.plankIpari))),
      flip: rotation >= 180,
    };
  }
}
