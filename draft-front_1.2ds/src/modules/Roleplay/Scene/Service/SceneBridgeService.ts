import { Graphics } from 'pixi.js';
import type { Texture } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { SCENE_STAIRS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_STAIRS';
import { sceneDeckFrame, sceneDeckRailShare, sceneDeckRailWidth } from '@/modules/Roleplay/Scene/Service/SceneDeck';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';
import { sceneStairSteps } from '@/modules/Roleplay/Scene/Service/SceneStairSurface';

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
  /**
   * Рисует мост, съезд или лестницу; `null` — элемент не прямоугольный.
   *
   * `surface` — готовая подложка марша: материал пола, разложенный досками по
   * проступям. Мост и съезд её не используют.
   */
  paint(
    element: SceneElement,
    resolution: number,
    zoom: number,
    blockedShare = 0,
    surface: Texture | null = null,
  ): Graphics | null {
    if (element.shape.type !== 'rect') {
      return null;
    }

    const metrics = this.metrics(element, resolution, zoom);
    if (element.props.kind === 'stairs') {
      return this.paintStairs(metrics, resolution, element.props.railing === true, surface);
    }

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

  /**
   * Лестница: подложка из материала пола и мягкие ступени.
   *
   * Тёмный подступенок и светлый носок отдельными плашками убраны: они читались
   * полосатым ковром. Вместо этого верхняя ступень бросает тень на нижнюю: она
   * темнеет у высокой кромки и сходит к низкой, а на ребре остаётся тонкий
   * светлый носок. Проступь светлеет к высокому концу, поэтому подъём виден без
   * высоты в рисунке. Ступени считаются от `elevation`: марш на два ипари при
   * ступени в восьмую ипари даёт шестнадцать ступеней. Направление подъёма — тот же
   * локальный «+» оси, что у `sceneRampSlope`, поэтому `props.rotation`
   * разворачивает марш. Ширины носка, граней и теней — доли короткой стороны,
   * а не экранные пиксели: первый рендер при другом зуме не даёт широких полос.
   *
   * Подложка — материал пола (`props.material`): у досок ряд приходится на
   * проступь и идёт от края до края, волокна — вдоль ширины. Поверх лежит
   * полупрозрачная подкраска, иначе марш сливается с полом. Под нижней ступенью
   * на полу лежит своя тень: марш стоит над полом, а не врезан в него.
   *
   * Перила — необязательные светлые балки по длинным сторонам. Их высота и
   * полоса в навигации те же, что у моста; включаются свойством `railing`.
   */
  private paintStairs(
    metrics: DeckMetrics,
    resolution: number,
    railed: boolean,
    surface: Texture | null,
  ): Graphics {
    const graphics = new Graphics();

    // Ступени занимают всю подложку: внутренний отступ читался тёмной рамкой
    // вокруг марша. Борт задают перила или сама тень, а не кант.
    const run = metrics.horizontal ? metrics.w : metrics.h;
    const steps = sceneStairSteps(metrics.rise, resolution);
    const tread = run / steps;
    const start = metrics.horizontal ? metrics.x : metrics.y;
    const nosing = Math.max(0.5, metrics.short * SCENE_STAIRS.nosingShare);

    // Тень нижней ступени ложится на пол за низким торцом.
    this.paintStairFootShadow(graphics, metrics, start);

    // Подложка: материал пола, у досок рисунок повёрнут по маршу.
    this.paintStairSurface(graphics, metrics, surface);

    /** Прямоугольник вдоль хода: `from…to` по длинной стороне. */
    const band = (from: number, to: number): void => {
      const length = Math.max(0.5, to - from);
      if (metrics.horizontal) {
        graphics.rect(from, metrics.y, length, metrics.h);
      } else {
        graphics.rect(metrics.x, from, metrics.w, length);
      }
    };

    for (let index = 0; index < steps; index += 1) {
      const from = start + tread * index;
      const to = from + tread;
      const share = steps === 1 ? 0 : index / (steps - 1);
      const tone = this.mixColor(SCENE_STAIRS.treadLow, SCENE_STAIRS.treadHigh, share);

      if (steps === 1) {
        // Без подъёма марш — ровная подложка. У одиночной ступени остаётся
        // только носок: тень на всю проступь читалась бы пандусом.
        if (metrics.rise > 0) {
          band(from, Math.min(to, from + nosing));
          graphics.fill({ color: SCENE_STAIRS.nosingColor, alpha: SCENE_STAIRS.nosingAlpha });
        }

        continue;
      }

      // Проступь: полупрозрачный тон, светлее к высокому концу. Материал
      // подложки остаётся виден — доска читается доской.
      band(from, to);
      graphics.fill({ color: tone, alpha: SCENE_STAIRS.treadAlpha });

      // Тень верхней ступени падает на нижнюю: темнеет высокая кромка
      // проступи, к низкой она сходит. У самой верхней ступени тени сверху
      // нет.
      if (index < steps - 1) {
        const shadowFrom = Math.max(from, to - tread * SCENE_STAIRS.shadeShare);
        const span = Math.max(0.5, to - shadowFrom);
        const slices = Math.max(1, SCENE_STAIRS.shadeSlices);
        for (let slice = 0; slice < slices; slice += 1) {
          // Полосы стыкуются вплотную: полупрозрачная заливка с нахлёстом
          // темнела бы на каждом шве и читалась частой линейкой.
          const fromPart = shadowFrom + (span * slice) / slices;
          const toPart = shadowFrom + (span * (slice + 1)) / slices;
          band(fromPart, Math.min(to, toPart));
          const part = (slice + 0.5) / slices;
          graphics.fill({ color: SCENE_STAIRS.shadeColor, alpha: SCENE_STAIRS.shadeAlpha * part });
        }
      }

      // Носок: тонкая светлая кромка на низкой стороне ступени.
      band(from, Math.min(to, from + nosing));
      graphics.fill({ color: SCENE_STAIRS.nosingColor, alpha: SCENE_STAIRS.nosingAlpha });
    }

    this.paintStairEdges(graphics, metrics);
    if (railed) {
      this.paintStairRails(graphics, metrics);
    }

    this.applyRotation(graphics, metrics);

    return graphics;
  }

  /**
   * Подложка марша: материал пола, выложенный досками по проступям.
   *
   * Текстура уже собрана на геометрию марша и в локальных координатах, поэтому
   * поворачивается вместе с элементом. Без неё остаётся ровный тон.
   */
  private paintStairSurface(
    graphics: Graphics,
    metrics: DeckMetrics,
    surface: Texture | null,
  ): void {
    if (surface === null) {
      graphics.rect(metrics.x, metrics.y, metrics.w, metrics.h).fill({ color: SCENE_STAIRS.baseColor });
    } else {
      graphics.rect(metrics.x, metrics.y, metrics.w, metrics.h).fill({ texture: surface });
    }

    // Подкраска: марш светлее пола и не читается его вырезом.
    graphics
      .rect(metrics.x, metrics.y, metrics.w, metrics.h)
      .fill({ color: SCENE_STAIRS.surfaceTint, alpha: SCENE_STAIRS.surfaceTintAlpha });
  }

  /**
   * Тень нижней ступени на пол.
   *
   * Марш стоит над полом, поэтому низкий торец отбрасывает тень наружу — туда
   * же, куда ступени выше бросают тень вниз по маршу. Полосы гаснут от торца.
   */
  private paintStairFootShadow(graphics: Graphics, metrics: DeckMetrics, start: number): void {
    if (metrics.rise <= 0) {
      return;
    }

    const depth = Math.max(1, metrics.short * SCENE_STAIRS.footShadowShare);
    const strips = Math.max(1, SCENE_STAIRS.footShadowStrips);
    for (let strip = 0; strip < strips; strip += 1) {
      const offset = (depth * strip) / strips;
      const size = Math.max(0.5, depth / strips);
      const alpha = SCENE_STAIRS.footShadowAlpha * (1 - strip / strips);
      if (metrics.horizontal) {
        graphics.rect(start - offset - size, metrics.y, size, metrics.h);
      } else {
        graphics.rect(metrics.x, start - offset - size, metrics.w, size);
      }
      graphics.fill({ color: SCENE_STAIRS.footShadowColor, alpha });
    }
  }

  /**
   * Затемнение у длинных краёв марша.
   *
   * Без перил подложка обрывалась ровной полосой, и марш читался наклейкой на
   * полу. Мягкая тень внутрь от края даёт борту толщину; перила, если они есть,
   * ложатся поверх и добавляют свою тень.
   */
  private paintStairEdges(graphics: Graphics, metrics: DeckMetrics): void {
    const depth = Math.max(1, metrics.short * SCENE_STAIRS.edgeShadeShare);
    const strips = Math.max(1, SCENE_STAIRS.edgeShadeStrips);
    for (let stripe = 0; stripe < strips; stripe += 1) {
      const offset = (depth * stripe) / strips;
      const size = Math.max(0.5, depth / strips);
      const alpha = SCENE_STAIRS.edgeShadeAlpha * (1 - stripe / strips);
      if (metrics.horizontal) {
        graphics.rect(metrics.x, metrics.y + offset, metrics.w, size);
        graphics.fill({ color: SCENE_STAIRS.edgeShadeColor, alpha });
        graphics.rect(metrics.x, metrics.y + metrics.h - offset - size, metrics.w, size);
        graphics.fill({ color: SCENE_STAIRS.edgeShadeColor, alpha });
      } else {
        graphics.rect(metrics.x + offset, metrics.y, size, metrics.h);
        graphics.fill({ color: SCENE_STAIRS.edgeShadeColor, alpha });
        graphics.rect(metrics.x + metrics.w - offset - size, metrics.y, size, metrics.h);
        graphics.fill({ color: SCENE_STAIRS.edgeShadeColor, alpha });
      }
    }
  }

  /**
   * Перила марша: светлые балки по длинным сторонам.
   *
   * Поперёк балки идёт плавный градиент от светлой внешней кромки к тёмной
   * внутренней; внутри марша лежит мягкая тень. Торцы закрыты заглушками,
   * столбиков нет — перила остаются линией, а не забором.
   */
  private paintStairRails(graphics: Graphics, metrics: DeckMetrics): void {
    const width = Math.max(1, metrics.rail);
    const line = Math.max(0.5, width * SCENE_STAIRS.railEdgeShare);
    const shadow = Math.max(1, metrics.short * SCENE_STAIRS.railShadowShare);
    const cap = Math.max(1, width * SCENE_STAIRS.railCapShare);
    const rainbow = 8;

    /** Одна балка: `outer` — внешняя кромка, `inner` — внутренняя. */
    const beam = (
      horizontal: boolean,
      outer: number,
      inner: number,
      crossStart: number,
      crossSize: number,
    ): void => {
      const span = inner - outer;
      const sign = span >= 0 ? 1 : -1;
      for (let slice = 0; slice < rainbow; slice += 1) {
        const fromPart = outer + (span * slice) / rainbow;
        const toPart = outer + (span * (slice + 1)) / rainbow;
        const low = Math.min(fromPart, toPart);
        const size = Math.max(0.5, Math.abs(toPart - fromPart));
        if (horizontal) {
          graphics.rect(crossStart, low, crossSize, size);
        } else {
          graphics.rect(low, crossStart, size, crossSize);
        }
        graphics.fill({ color: this.railTone((slice + 0.5) / rainbow) });
      }

      // Тёмная внутренняя грань.
      const edge = inner - sign * line;
      if (horizontal) {
        graphics.rect(crossStart, Math.min(edge, edge + line), crossSize, line);
      } else {
        graphics.rect(Math.min(edge, edge + line), crossStart, line, crossSize);
      }
      graphics.fill({ color: SCENE_STAIRS.railDark, alpha: 0.65 });

      // Мягкая тень на ступенях: три полосы, гаснущие от балки внутрь марша.
      for (let stripe = 0; stripe < 3; stripe += 1) {
        const fromPart = inner + sign * (shadow * stripe) / 3;
        const size = Math.max(0.5, shadow / 3);
        if (horizontal) {
          graphics.rect(crossStart, Math.min(fromPart, fromPart + sign * size), crossSize, size);
        } else {
          graphics.rect(Math.min(fromPart, fromPart + sign * size), crossStart, size, crossSize);
        }
        graphics.fill({
          color: SCENE_STAIRS.railShadow,
          alpha: SCENE_STAIRS.railShadowAlpha * (1 - stripe / 3),
        });
      }

      // Заглушки на торцах балки.
      if (horizontal) {
        graphics.rect(crossStart, Math.min(outer, inner), cap, width).fill({ color: SCENE_STAIRS.railDark, alpha: 0.9 });
        graphics
          .rect(crossStart + crossSize - cap, Math.min(outer, inner), cap, width)
          .fill({ color: SCENE_STAIRS.railDark, alpha: 0.9 });
      } else {
        graphics.rect(Math.min(outer, inner), crossStart, width, cap).fill({ color: SCENE_STAIRS.railDark, alpha: 0.9 });
        graphics
          .rect(Math.min(outer, inner), crossStart + crossSize - cap, width, cap)
          .fill({ color: SCENE_STAIRS.railDark, alpha: 0.9 });
      }
    };

    if (metrics.horizontal) {
      beam(true, metrics.y, metrics.y + width, metrics.x, metrics.w);
      beam(true, metrics.y + metrics.h, metrics.y + metrics.h - width, metrics.x, metrics.w);
    } else {
      beam(false, metrics.x, metrics.x + width, metrics.y, metrics.h);
      beam(false, metrics.x + metrics.w, metrics.x + metrics.w - width, metrics.y, metrics.h);
    }
  }

  /** Тон перил: светлый край, тело и тёмная внутренняя грань. */
  private railTone(share: number): number {
    const t = Math.max(0, Math.min(1, share));

    return t < 0.5
      ? this.mixColor(SCENE_STAIRS.railLight, SCENE_STAIRS.railMid, t * 2)
      : this.mixColor(SCENE_STAIRS.railMid, SCENE_STAIRS.railDark, (t - 0.5) * 2);
  }

  /** Смешение двух цветов: тон проступи растёт к высокому концу. */
  private mixColor(from: number, to: number, share: number): number {
    const t = Math.max(0, Math.min(1, share));
    const channel = (shift: number): number => {
      const start = (from >> shift) & 0xff;
      const end = (to >> shift) & 0xff;

      return Math.round(start + (end - start) * t);
    };

    return (channel(16) << 16) | (channel(8) << 8) | channel(0);
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
      rail: sceneDeckRailWidth(frame, sceneDeckRailShare(element)),
      inset: Math.max(1, short * SCENE_BRIDGE.deckInset),
      planks: Math.max(1, Math.round((shape.w >= shape.h ? shape.w : shape.h) / (base * SCENE_BRIDGE.plankIpari))),
      rotation: sceneRotation(element),
    };
  }
}
