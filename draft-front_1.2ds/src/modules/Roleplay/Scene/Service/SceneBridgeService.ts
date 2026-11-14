import { Graphics } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { sceneDeckFrame, sceneDeckRailWidth } from '@/modules/Roleplay/Scene/Service/SceneDeck';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

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
  /** Подъём верхней поверхности: влияет только на тень, не на проекцию. */
  rise: number;
  /** Толщина настила: вертикальный размер для Game, в рисунке — край. */
  thickness: number;
  rail: number;
  inset: number;
  planks: number;
  /** Поворот вокруг центра прямоугольника, градусы. */
  rotation: number;
}

/**
 * Мост и съезд: плоский настил, доски, перила и тень.
 *
 * Сцена top-down: мост — часть пола, а не 3D-коробка. Поэтому у настила нет
 * боковой грани, свай и ватерлинии — только тёмная кромка, доски и мягкая
 * тень на воде. Высота (`props.elevation`) и толщина (`props.height`) остаются
 * данными для Game: по ним считаются зазор под настилом и рёбра доступа.
 *
 * Ориентация — по длинной стороне прямоугольника, `props.rotation` поворачивает
 * настил вокруг центра. Толщина линий переводится из экранных пикселей через
 * зум.
 */
export class SceneBridgeService {
  /** Рисует мост или съезд; `null` — элемент не прямоугольный. */
  paint(element: SceneElement, resolution: number, zoom: number, blockedShare = 0): Graphics | null {
    if (element.shape.type !== 'rect') {
      return null;
    }

    const metrics = this.metrics(element, resolution, zoom);

    return element.props.kind === 'ramp' ? this.paintRamp(metrics, blockedShare) : this.paintBridge(metrics);
  }

  /** Мост: ровный настил с перилами. */
  private paintBridge(metrics: DeckMetrics): Graphics {
    const graphics = new Graphics();
    this.paintShadow(graphics, metrics);
    this.paintDeck(graphics, metrics);
    this.paintEndCaps(graphics, metrics);
    this.paintRails(graphics, metrics);
    this.applyRotation(graphics, metrics);

    return graphics;
  }

  /** Съезд: наклонный настил со ступенями. */
  private paintRamp(metrics: DeckMetrics, blockedShare: number): Graphics {
    const graphics = new Graphics();
    this.paintShadow(graphics, metrics);
    this.paintDeck(graphics, metrics, true);
    this.paintRampTint(graphics, metrics);
    this.paintRampContact(graphics, metrics, blockedShare);
    this.paintRampCleats(graphics, metrics);
    this.paintRampEnds(graphics, metrics);
    this.paintRails(graphics, metrics);
    this.applyRotation(graphics, metrics);

    return graphics;
  }

  /** Мягкая тень на воде: смещение растёт с подъёмом, но грань не рисуется. */
  private paintShadow(graphics: Graphics, metrics: DeckMetrics): void {
    const offset = metrics.rise * 0.12 + metrics.thickness * 0.2;
    const spread = Math.max(2, metrics.short * 0.04);
    for (let layer = 3; layer >= 1; layer -= 1) {
      const grow = spread * layer;
      graphics
        .roundRect(
          metrics.x - grow + offset,
          metrics.y - grow + offset,
          metrics.w + grow * 2,
          metrics.h + grow * 2,
          Math.max(1, metrics.short * 0.08),
        )
        .fill({ color: SCENE_BRIDGE.shadowColor, alpha: SCENE_BRIDGE.shadowAlpha / (layer + 2) });
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

  /** Затемнение к высокому концу съезда: полосы читаются как подъём. */
  private paintRampTint(graphics: Graphics, metrics: DeckMetrics): void {
    const bands = 4;
    for (let index = 0; index < bands; index += 1) {
      const from = index / bands;
      const to = (index + 1) / bands;
      if (metrics.horizontal) {
        graphics
          .rect(
            metrics.x + metrics.w * (0.45 + from * 0.55),
            metrics.y + metrics.inset,
            metrics.w * (to - from) * 0.55,
            Math.max(1, metrics.h - metrics.inset * 2),
          )
          .fill({ color: SCENE_BRIDGE.shadowColor, alpha: 0.02 + index * 0.025 });
      } else {
        graphics
          .rect(
            metrics.x + metrics.inset,
            metrics.y + metrics.h * (0.45 + from * 0.55),
            Math.max(1, metrics.w - metrics.inset * 2),
            metrics.h * (to - from) * 0.55,
          )
          .fill({ color: SCENE_BRIDGE.shadowColor, alpha: 0.02 + index * 0.025 });
      }
    }
  }

  /** Поперечные ступени съезда: читаются как наклонные доски. */
  /**
   * Подошва съезда: низкая часть лежит на земле, под ней нет просвета.
   *
   * Плоский настил об этом не говорит, поэтому низ затемняется как насыпь.
   * Заливка прозрачная — доски и ступени остаются видны; линия на границе
   * показывает, где просвет появляется.
   */
  private paintRampContact(graphics: Graphics, metrics: DeckMetrics, share: number): void {
    const blocked = Math.min(1, Math.max(0, share));
    if (blocked <= 0) {
      return;
    }

    const innerW = Math.max(1, metrics.w - metrics.inset * 2);
    const innerH = Math.max(1, metrics.h - metrics.inset * 2);
    const fill = { color: SCENE_BRIDGE.shadowColor, alpha: SCENE_BRIDGE.rampContactAlpha };
    if (metrics.horizontal) {
      const width = Math.max(1, innerW * blocked);
      graphics.rect(metrics.x + metrics.inset, metrics.y + metrics.inset, width, innerH).fill(fill);
      graphics
        .moveTo(metrics.x + metrics.inset + width, metrics.y + metrics.inset)
        .lineTo(metrics.x + metrics.inset + width, metrics.y + metrics.h - metrics.inset)
        .stroke({ color: SCENE_BRIDGE.edgeColor, width: metrics.line, alpha: 0.5 });
    } else {
      const height = Math.max(1, innerH * blocked);
      graphics.rect(metrics.x + metrics.inset, metrics.y + metrics.inset, innerW, height).fill(fill);
      graphics
        .moveTo(metrics.x + metrics.inset, metrics.y + metrics.inset + height)
        .lineTo(metrics.x + metrics.w - metrics.inset, metrics.y + metrics.inset + height)
        .stroke({ color: SCENE_BRIDGE.edgeColor, width: metrics.line, alpha: 0.5 });
    }
  }

  private paintRampCleats(graphics: Graphics, metrics: DeckMetrics): void {
    const cleat = Math.max(1, metrics.line * 1.8);
    for (
      let index = SCENE_BRIDGE.rampCleatEveryPlanks;
      index < metrics.planks;
      index += SCENE_BRIDGE.rampCleatEveryPlanks
    ) {
      if (metrics.horizontal) {
        const px = metrics.x + (metrics.w * index) / metrics.planks;
        graphics
          .moveTo(px, metrics.y + metrics.inset)
          .lineTo(px, metrics.y + metrics.h - metrics.inset)
          .stroke({ color: SCENE_BRIDGE.edgeColor, width: cleat, alpha: 0.75 });
        graphics
          .moveTo(px + cleat, metrics.y + metrics.inset)
          .lineTo(px + cleat, metrics.y + metrics.h - metrics.inset)
          .stroke({ color: SCENE_BRIDGE.railHighlightColor, width: metrics.line * 0.6, alpha: 0.35 });
      } else {
        const py = metrics.y + (metrics.h * index) / metrics.planks;
        graphics
          .moveTo(metrics.x + metrics.inset, py)
          .lineTo(metrics.x + metrics.w - metrics.inset, py)
          .stroke({ color: SCENE_BRIDGE.edgeColor, width: cleat, alpha: 0.75 });
        graphics
          .moveTo(metrics.x + metrics.inset, py + cleat)
          .lineTo(metrics.x + metrics.w - metrics.inset, py + cleat)
          .stroke({ color: SCENE_BRIDGE.railHighlightColor, width: metrics.line * 0.6, alpha: 0.35 });
      }
    }
  }

  /** Порог съезда: низкий конец светлее, высокий — тёмная балка. */
  private paintRampEnds(graphics: Graphics, metrics: DeckMetrics): void {
    const cap = Math.max(2, metrics.short * SCENE_BRIDGE.endCapWidth);
    if (metrics.horizontal) {
      graphics.rect(metrics.x, metrics.y, cap, metrics.h).fill({ color: SCENE_BRIDGE.railHighlightColor, alpha: 0.35 });
      graphics
        .rect(metrics.x + metrics.w - cap, metrics.y, cap, metrics.h)
        .fill({ color: SCENE_BRIDGE.edgeColor, alpha: 0.95 });
    } else {
      graphics.rect(metrics.x, metrics.y, metrics.w, cap).fill({ color: SCENE_BRIDGE.railHighlightColor, alpha: 0.35 });
      graphics
        .rect(metrics.x, metrics.y + metrics.h - cap, metrics.w, cap)
        .fill({ color: SCENE_BRIDGE.edgeColor, alpha: 0.95 });
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

  /** Поворот всего настила вокруг центра прямоугольника. */
  private applyRotation(graphics: Graphics, metrics: DeckMetrics): void {
    if (metrics.rotation === 0) {
      return;
    }

    const centerX = metrics.x + metrics.w / 2;
    const centerY = metrics.y + metrics.h / 2;
    graphics.pivot.set(centerX, centerY);
    graphics.position.set(centerX, centerY);
    graphics.rotation = (metrics.rotation * Math.PI) / 180;
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
    const frame = sceneDeckFrame(element);
    if (frame === null) {
      throw new Error('SceneBridgeService.metrics требует прямоугольник');
    }
    const short = Math.max(1, Math.min(shape.w, shape.h));
    const rise = Math.max(0, element.props.elevation ?? 0);
    const thickness = Math.max(1, element.props.height ?? base * SCENE_BRIDGE.thicknessIpari);

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
      rail: sceneDeckRailWidth(frame),
      inset: Math.max(1, short * SCENE_BRIDGE.deckInset),
      planks: Math.max(1, Math.round((shape.w >= shape.h ? shape.w : shape.h) / (base * SCENE_BRIDGE.plankIpari))),
      rotation: sceneRotation(element),
    };
  }
}
