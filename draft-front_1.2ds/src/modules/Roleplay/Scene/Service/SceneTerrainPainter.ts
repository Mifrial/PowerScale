import type { Container } from 'pixi.js';
import { Graphics } from 'pixi.js';
import type { FillPattern } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneTerrainPatternService } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import type {
  SceneTerrainMaterial,
  SceneTerrainTextureService,
} from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

/** Ширина мокрого берега в ипари. */
const WET_IPARI = 0.3;

/** Шаблоны, для которых есть фотоматериал CC0. */
const MATERIALS: Record<string, SceneTerrainMaterial | undefined> = {
  grass: 'grass',
  dirt: 'dirt',
  stone: 'stone',
  sand: 'sand',
  mud: 'mud',
};

/**
 * Материалы террейна: поверхность, кромка и вода.
 *
 * Поверхность заливается бесшовной текстурой в глобальном пространстве и
 * разбавляется крупной вариацией, чтобы тайл не читался штампом. Вода живёт
 * в `SceneWaterService`: здесь только её дно, сырой берег и кромка.
 */
export class SceneTerrainPainter {
  constructor(
    private readonly patterns: SceneTerrainPatternService,
    private readonly materials: SceneTerrainTextureService,
  ) {}

  /** Поверхность террейна: материал плюс крупная вариация. */
  paintSurface(container: Container, element: SceneElement, resolution: number, zoom: number): Graphics {
    const graphics = new Graphics();
    this.trace(graphics, element.shape, zoom);
    graphics.fill({ fill: this.materialPattern(element.props.kind, element.id, resolution), alpha: 1 });
    container.addChild(graphics);
    this.paintMacro(container, element, resolution, zoom);

    return graphics;
  }

  /** Кромка террейна: расширенная лента или наружная полоса плюс мокрый край. */
  paintShore(container: Container, element: SceneElement, resolution: number, zoom: number): Graphics | null {
    const shoreKind = element.props.shoreKind ?? 'none';
    const width = element.props.shoreWidth ?? 0;
    if (shoreKind === 'none' || width <= 0) {
      return null;
    }

    const pattern = this.materialPattern(shoreKind, element.id + 17, resolution);
    const graphics = new Graphics();
    if (element.shape.type === 'path') {
      const outline = sceneRibbonService.outline(
        element.shape.points,
        element.shape.handles ?? [],
        element.shape.widths,
        sceneCurveService.toleranceFor(zoom),
        width,
      );
      graphics.poly(outline.flat()).fill({ fill: pattern, alpha: 1 });
    } else {
      this.trace(graphics, element.shape, zoom);
      graphics.stroke({ fill: pattern, width: width * 2, alpha: 1, join: 'round', cap: 'round' });
    }

    container.addChild(graphics);
    this.paintWetEdge(container, element, shoreKind, resolution, zoom);

    return graphics;
  }

  /** Сырой берег: тёмная полоса сразу за урезом, чтобы край не был резким. */
  paintWaterBank(container: Container, element: SceneElement, resolution: number, zoom: number): void {
    const graphics = new Graphics();
    for (const [width, alpha] of [
      [0.42, 0.1],
      [0.2, 0.16],
    ]) {
      this.trace(graphics, element.shape, zoom);
      graphics.stroke({
        color: 0x1d2f38,
        width: Math.max(1, resolution * width),
        alpha,
        alignment: 0,
        join: 'round',
      });
    }

    container.addChild(graphics);
  }

  /** Дно водоёма: грунт под водой, приглушённый глубиной. */
  paintWaterBed(container: Container, element: SceneElement, resolution: number, zoom: number): void {
    const bed = this.materials.texture('bed');
    if (bed !== null) {
      const texture = new Graphics();
      this.trace(texture, element.shape, zoom);
      texture.fill({
        fill: this.patterns.patternOf(bed, this.materials.period('bed', resolution), element.id),
        alpha: 1,
      });
      container.addChild(texture);
    }
  }

  /** Крупная вариация материала: сбивает сетку повторов тайла. */
  private paintMacro(container: Container, element: SceneElement, resolution: number, zoom: number): void {
    if (MATERIALS[element.props.kind ?? ''] === undefined) {
      return;
    }

    const macro = this.materials.texture('macro');
    if (macro === null) {
      return;
    }

    const graphics = new Graphics();
    this.trace(graphics, element.shape, zoom);
    graphics.fill({ fill: this.patterns.patternOf(macro, this.materials.period('macro', resolution), element.id + 5) });
    container.addChild(graphics);
  }

  /** Мокрый край кромки: узкая полоса сразу за урезом. */
  private paintWetEdge(
    container: Container,
    element: SceneElement,
    shoreKind: string,
    resolution: number,
    zoom: number,
  ): void {
    const wet = this.materials.texture('wetsand');
    if (wet === null || shoreKind !== 'sand') {
      return;
    }

    const pattern = this.patterns.patternOf(wet, this.materials.period('wetsand', resolution), element.id + 31);
    const path = (graphics: Graphics): Graphics => {
      if (element.shape.type === 'path') {
        const outline = sceneRibbonService.outline(
          element.shape.points,
          element.shape.handles ?? [],
          element.shape.widths,
          sceneCurveService.toleranceFor(zoom),
        );

        graphics.poly(outline.flat());

        return graphics;
      }

      this.trace(graphics, element.shape, zoom);

      return graphics;
    };

    // Сырость нарастает к урезу: несколько полос от широкой и бледной к узкой
    // и плотной дают градиент, а не ровную тёмную ленту по всему берегу.
    const bands: [number, number][] = [
      [WET_IPARI, 0.22],
      [WET_IPARI * 0.6, 0.3],
      [WET_IPARI * 0.28, 0.4],
    ];
    const graphics = new Graphics();
    for (const [width, alpha] of bands) {
      path(graphics).stroke({
        fill: pattern,
        width: Math.max(1, resolution * width),
        alignment: 0,
        alpha,
        join: 'round',
      });
    }

    container.addChild(graphics);
  }

  /** Паттерн материала: CC0-текстура, а без неё — процедурный шаблон. */
  private materialPattern(kind: string | undefined, elementId: number, resolution: number): FillPattern {
    const material = MATERIALS[kind ?? ''];
    const texture = material === undefined ? null : this.materials.texture(material);
    if (material === undefined || texture === null) {
      return this.patterns.pattern(kind, elementId, resolution);
    }

    return this.patterns.patternOf(texture, this.materials.period(material, resolution), elementId);
  }

  /** Путь фигуры в текущем контексте Graphics. */
  private trace(graphics: Graphics, shape: SceneShape, zoom: number): void {
    switch (shape.type) {
      case 'rect':
        graphics.rect(shape.x, shape.y, shape.w, shape.h);
        break;
      case 'circle':
        graphics.circle(shape.cx, shape.cy, shape.r);
        break;
      case 'polygon': {
        const tolerance = sceneCurveService.toleranceFor(zoom);
        const outline = sceneCurveService.flattenContour(shape.points, shape.handles ?? [], tolerance);
        graphics.poly(outline.flat()).closePath();
        break;
      }
      case 'path': {
        const tolerance = sceneCurveService.toleranceFor(zoom);
        const outline = sceneRibbonService.outline(shape.points, shape.handles ?? [], shape.widths, tolerance);
        graphics.poly(outline.flat()).closePath();
        break;
      }
      case 'point':
        graphics.circle(shape.x, shape.y, 9 / zoom);
        break;
      case 'wall':
        graphics.poly(shape.points.flat()).closePath();
        break;
    }
  }
}
