import type { Container } from 'pixi.js';
import { Graphics } from 'pixi.js';
import type { FillPattern, Texture } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneTerrainBlendService } from '@/modules/Roleplay/Scene/Service/SceneTerrainBlendService';
import type { SceneTerrainPatternService } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import type {
  SceneTerrainMaterial,
  SceneTerrainTextureService,
} from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
import { SCENE_TERRAIN_BLEND } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_BLEND';
import { SCENE_GRASS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_GRASS';
import { patternOffset } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
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
    private readonly blend: SceneTerrainBlendService,
  ) {}

  /** Поверхность террейна: материал плюс крупная вариация. */
  paintSurface(container: Container, element: SceneElement, resolution: number, zoom: number): Graphics {
    const graphics = new Graphics();
    this.trace(graphics, element.shape, zoom);
    graphics.fill({ fill: this.materialPattern(element.props.kind, element.id, resolution), alpha: 1 });
    container.addChild(graphics);
    this.paintDetail(container, element, resolution, zoom);
    this.paintMacro(container, element, resolution, zoom);
    // Осыпь поверх края: тот же материал, но рваной полосой наружу. Если у
    // элемента есть кромка, наружу уходит её материал, и вторая осыпь только
    // перекрыла бы мокрый край.
    const hasShore = (element.props.shoreKind ?? 'none') !== 'none' && (element.props.shoreWidth ?? 0) > 0;
    if (!hasShore) {
      this.paintSpill(container, element, element.props.kind, element.id, resolution, zoom, 0, true);
    }

    return graphics;
  }

  /** Кромка террейна: расширенная лента или наружная полоса плюс мокрый край. */
  paintShore(
    container: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number] = [0, 0],
    wet = true,
  ): Graphics | null {
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
        endPads[0],
        endPads[1],
      );
      graphics.poly(outline.flat()).fill({ fill: pattern, alpha: 1 });
    } else {
      this.trace(graphics, element.shape, zoom, endPads);
      graphics.stroke({ fill: pattern, width: width * 2, alpha: 1, join: 'round', cap: 'round' });
    }

    container.addChild(graphics);
    // Осыпь идёт от уреза и перекрывает полосу кромки тем же материалом.
    // Мокрый край рисуется после и остаётся поверх песка.
    this.paintSpill(container, element, shoreKind, element.id + 17, resolution, zoom, width, false);
    if (wet) {
      this.paintWetEdge(container, element, shoreKind, resolution, zoom, endPads);
    }

    return graphics;
  }

  /** Сырой берег: тёмная полоса сразу за урезом, чтобы край не был резким. */
  paintWaterBank(
    container: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number] = [0, 0],
  ): void {
    const graphics = new Graphics();
    for (const [width, alpha] of [
      [0.42, 0.1],
      [0.2, 0.16],
    ]) {
      this.trace(graphics, element.shape, zoom, endPads);
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

    const isGrass = element.props.kind === 'grass';
    const macro = isGrass ? this.patterns.grassMacro() : this.materials.texture('macro');
    if (macro === null) {
      return;
    }

    const period = isGrass
      ? Math.max(1, resolution) * SCENE_GRASS.macroIpari
      : this.materials.period('macro', resolution);
    const graphics = new Graphics();
    this.trace(graphics, element.shape, zoom);
    graphics.fill({ fill: this.patterns.patternOf(macro, period, element.id + 5) });
    container.addChild(graphics);
  }

  /** Мелкая деталь травы: лезвия и зерно поверх фотоматериала. */
  private paintDetail(container: Container, element: SceneElement, resolution: number, zoom: number): void {
    if (element.props.kind !== 'grass') {
      return;
    }

    const period = Math.max(1, resolution) * SCENE_GRASS.detailIpari;
    const graphics = new Graphics();
    this.trace(graphics, element.shape, zoom);
    graphics.fill({ fill: this.patterns.patternOf(this.patterns.grassDetail(), period, element.id + 11) });
    container.addChild(graphics);
  }

  /**
   * Рваная кромка: материал элемента неровно наползает на соседа.
   *
   * Полоса начинается на контуре фигуры и тянется наружу. `solid` — сколько
   * ширины залить без шума: у кромки воды это сама полоса берега, иначе осыпь
   * съела бы её край.
   */
  private paintSpill(
    container: Container,
    element: SceneElement,
    kind: string | undefined,
    elementId: number,
    resolution: number,
    zoom: number,
    solid: number,
    macro: boolean,
  ): void {
    const outline = this.outlineOf(element.shape, zoom);
    if (outline === null) {
      return;
    }

    const material = this.spillMaterial(kind, elementId, resolution);
    const isGrass = kind === 'grass' && macro;
    const macroTexture = macro ? (isGrass ? this.patterns.grassMacro() : this.materials.texture('macro')) : null;
    const macroPeriod = isGrass
      ? Math.max(1, resolution) * SCENE_GRASS.macroIpari
      : this.materials.period('macro', resolution);
    const overlay = isGrass ? this.patterns.grassDetail() : null;
    const overlayPeriod = Math.max(1, resolution) * SCENE_GRASS.detailIpari;
    this.blend.spill(container, outline, {
      material: material.texture,
      period: material.period,
      offset: material.offset,
      macro: macroTexture,
      macroPeriod,
      macroOffset: patternOffset(elementId + 5, macroPeriod),
      macroMix: macroTexture === null ? 0 : 1,
      overlay,
      overlayPeriod,
      overlayOffset: patternOffset(elementId + 11, overlayPeriod),
      overlayMix: overlay === null ? 0 : 1,
      solid,
      ragged: resolution * SCENE_TERRAIN_BLEND.spillIpari,
      base: SCENE_TERRAIN_BLEND.base,
      amp: SCENE_TERRAIN_BLEND.amp,
      noiseScale: 1 / (resolution * SCENE_TERRAIN_BLEND.noiseIpari),
      fineScale: 1 / (resolution * SCENE_TERRAIN_BLEND.fineIpari),
      fineAmp: SCENE_TERRAIN_BLEND.fineAmp,
      soft: resolution * SCENE_TERRAIN_BLEND.softIpari,
      alpha: 1,
      seed: this.spillSeed(elementId),
    });
  }

  /** Текстура, период и сдвиг материала: те же, что у заливки поверхности. */
  private spillMaterial(
    kind: string | undefined,
    elementId: number,
    resolution: number,
  ): { texture: Texture; period: number; offset: [number, number] } {
    const material = MATERIALS[kind ?? ''];
    const texture = material === undefined ? null : this.materials.texture(material);
    if (material !== undefined && texture !== null) {
      const period = this.materials.period(material, resolution);

      return { texture, period, offset: patternOffset(elementId, period) };
    }

    const period = this.patterns.period(kind, resolution);

    return { texture: this.patterns.texture(kind), period, offset: patternOffset(elementId, period) };
  }

  /** Сдвиг шума кромки: соседние элементы рвутся по-разному. */
  private spillSeed(elementId: number): [number, number] {
    const seed = Math.abs(Math.trunc(elementId)) * 2654435761;

    return [((seed % 1024) / 1024) * 17, (((seed >>> 10) % 1024) / 1024) * 17];
  }

  /** Замкнутый контур фигуры: по нему строится рваная кромка. */
  private outlineOf(shape: SceneShape, zoom: number): ScenePoint[] | null {
    const tolerance = sceneCurveService.toleranceFor(zoom);
    switch (shape.type) {
      case 'rect':
        return [
          [shape.x, shape.y],
          [shape.x + shape.w, shape.y],
          [shape.x + shape.w, shape.y + shape.h],
          [shape.x, shape.y + shape.h],
        ];
      case 'circle': {
        const segments = Math.max(32, Math.min(512, Math.ceil((Math.PI * 2 * shape.r * zoom) / 4)));
        const points: ScenePoint[] = [];
        for (let index = 0; index < segments; index += 1) {
          const angle = (index / segments) * Math.PI * 2;
          points.push([shape.cx + Math.cos(angle) * shape.r, shape.cy + Math.sin(angle) * shape.r]);
        }

        return points;
      }
      case 'polygon':
        return sceneCurveService.flattenContour(shape.points, shape.handles ?? [], tolerance);
      case 'path':
        return sceneRibbonService.outline(shape.points, shape.handles ?? [], shape.widths, tolerance);
      default:
        return null;
    }
  }

  /** Мокрый край кромки: узкая полоса сразу за урезом. */
  private paintWetEdge(
    container: Container,
    element: SceneElement,
    shoreKind: string,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number] = [0, 0],
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
          0,
          endPads[0],
          endPads[1],
        );

        graphics.poly(outline.flat());

        return graphics;
      }

      this.trace(graphics, element.shape, zoom, endPads);

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
  private trace(
    graphics: Graphics,
    shape: SceneShape,
    zoom: number,
    endPads: readonly [number, number] = [0, 0],
  ): void {
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
        const outline = sceneRibbonService.outline(
          shape.points,
          shape.handles ?? [],
          shape.widths,
          tolerance,
          0,
          endPads[0],
          endPads[1],
        );
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
