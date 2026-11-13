import { Mesh, MeshGeometry, Sprite, earcut } from 'pixi.js';
import type { Container, Shader, Texture } from 'pixi.js';
import { SCENE_WATER_CAUSTICS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_WATER_CAUSTICS';
import { SCENE_WATER_LIGHT } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_WATER_LIGHT';
import { SCENE_WATER_RIVER } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_WATER_RIVER';
import { SCENE_WATER_SHADER } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_WATER_SHADER';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { patternOffset } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import type { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
import { SceneWaterBodyShader } from '@/modules/Roleplay/Scene/Service/SceneWaterBodyShader';
import { SceneWaterCausticsShader } from '@/modules/Roleplay/Scene/Service/SceneWaterCausticsShader';
import { SceneWaterDepthService } from '@/modules/Roleplay/Scene/Service/SceneWaterDepthService';
import type { SceneWaterDepth } from '@/modules/Roleplay/Scene/Service/SceneWaterDepthService';
import { SceneWaterMeshService } from '@/modules/Roleplay/Scene/Service/SceneWaterMeshService';
import type { WaterStrip } from '@/modules/Roleplay/Scene/Service/SceneWaterMeshService';
import { SceneRiverBodyShader } from '@/modules/Roleplay/Scene/Service/SceneRiverBodyShader';
import { SceneRiverSurfaceShader } from '@/modules/Roleplay/Scene/Service/SceneRiverSurfaceShader';
import { SceneWaterSurfaceShader } from '@/modules/Roleplay/Scene/Service/SceneWaterSurfaceShader';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

/** Река на шейдерах: тело рисует дно само, отдельная текстура дна не нужна. */
export function isShaderRiver(element: SceneElement): boolean {
  return SCENE_WATER_SHADER.river && SCENE_WATER_RIVER.enabled && element.shape.type === 'path';
}

/** Стоячая вода на шейдерах: спокойный пресет для прудов и озёр. */
export function isShaderStill(element: SceneElement): boolean {
  return SCENE_WATER_SHADER.still && element.shape.type !== 'path';
}

/** Любая вода, которую рисуют шейдеры, а не текстуры. */
export function isShaderWater(element: SceneElement): boolean {
  return isShaderRiver(element) || isShaderStill(element);
}

/** Узел воды: обновление анимации по времени. */
export interface SceneWaterNode {
  update(seconds: number): void;
}

/** Геометрия поверхности: позиции, треугольники и координаты вдоль русла. */
interface WaterGeometry {
  positions: Float32Array;
  indices: Uint32Array;
  /** Длина по осевой для каждой вершины; есть только у ленты. */
  along?: Float32Array;
  /** Смещение поперёк осевой для каждой вершины; есть только у ленты. */
  across?: Float32Array;
}

/** Слой воды: материал, масштаб тайла, поворот выборки и дрейф. */
interface WaterLayer {
  material: 'ripples' | 'glints' | 'caustics';
  periodIpari: number;
  angle: number;
  alpha: number;
  speedIpari: number;
  /** Смещение дрейфа относительно направления течения. */
  skew: number;
  /** Аддитивное смешивание для бликов. */
  additive?: boolean;
  /** Размер тайла на экране, ниже которого слой гаснет, в пикселях. */
  fadeTile?: number;
}

/** Слой пены: ширина от базовой полосы, скорость вдоль берега, накат. */
interface FoamLayer {
  width: number;
  periodScale: number;
  alpha: number;
  speedIpari: number;
  offset: number;
  /** Амплитуда наката как доля ширины. */
  lap: number;
  /** Частота наката, герц. */
  lapRate: number;
}

/** Движение слоя: снос вдоль русла или вихрь вокруг центра. */
type WaterMotion = { kind: 'flow'; drift: ScenePoint } | { kind: 'swirl'; center: ScenePoint; rate: number };

/** Каустика по дну: подсвечивает, а не закрашивает. */
const CAUSTICS_ALPHA = 0.2;

/** Тайл каустики в ипари. */
const CAUSTICS_PERIOD_IPARI = 1.7;

/** Скорость каустики, ипари в секунду. */
const CAUSTICS_SPEED_IPARI = 0.14;

/** Скорость течения по умолчанию, ипари в секунду. */
const FLOW_SPEED_IPARI = 0.25;

/**
 * Слои плоскости воды: муть каустики и блики.
 *
 * Рябь с диагональной текстурой убрана: её полосы читались как тигриные
 * полоски вдоль русла и не отделялись от мути. Оставшиеся слои едут с разной
 * скоростью, поэтому вода не стоит на месте.
 */
const PLANE_LAYERS: WaterLayer[] = [
  {
    material: 'caustics',
    periodIpari: 2.1,
    angle: Math.PI / 5,
    alpha: 0.12,
    speedIpari: FLOW_SPEED_IPARI * 1.6,
    skew: 0.4,
  },
  {
    material: 'glints',
    periodIpari: 1.2,
    angle: Math.PI / 7,
    alpha: 0.5,
    speedIpari: FLOW_SPEED_IPARI * 3.2,
    skew: -0.3,
    additive: true,
  },
];

/** Крупная вариация тона воды: множитель, сбивающий сетку повторов. */
const MACRO_IPARI = 44;

/** Скорость макро-вариации, ипари в секунду. */
const MACRO_SPEED_IPARI = 0.04;

/** Тайл на экране, ниже которого слой начинает гаснуть, в пикселях. */
const MIN_TILE_PX = 26;

/** Медленный вихрь замкнутой воды: радиан в секунду. */
const SWIRL_RATE = 0.025;

/** Насколько тише плоскость у замкнутого водоёма: у лужи нет течения. */
const POND_ALPHA = 0.72;

/** Во сколько раз глуше каустика у замкнутого водоёма. */
const POND_CAUSTICS = 0.55;

/**
 * Слои пены: рваная полоса по урезу и широкая полупрозрачная дымка под ней.
 *
 * Вдоль берега пена едет медленно вниз по течению, а основное движение —
 * накат поперёк: полоса то наступает на воду, то отходит.
 */
const FOAM_LAYERS: FoamLayer[] = [
  { width: 1.1, periodScale: 1, alpha: 0.9, speedIpari: 0.045, offset: 0.13, lap: 0.5, lapRate: 0.17 },
  { width: 2, periodScale: 2.4, alpha: 0.3, speedIpari: 0.028, offset: 0.61, lap: 0.35, lapRate: 0.11 },
];

/** Ширина полосы пены в ипари. */
const FOAM_IPARI = 0.4;

/**
 * Растяжение тайла пены вдоль берега.
 *
 * Текстура квадратнее, чем полоса: без растяжения рисунок повторялся бы
 * каждые полтора метра и читался обоями.
 */
const FOAM_STRETCH = 2.5;

/** Доля ширины пены, уходящая на воду: остальное ложится на берег. */
const FOAM_WATER_SHARE = 0.7;

/** Разбиение окружности для воды-круга. */
const CIRCLE_SEGMENTS = 72;

/** Полный оборот в радианах. */
const TAU = Math.PI * 2;

/** Узел пены: меш, его геометрия и данные для наката. */
interface FoamNode {
  mesh: Mesh;
  positions: Float32Array;
  uvs: Float32Array;
  base: Float32Array;
  strip: WaterStrip;
  drift: number;
  offset: number;
  layer: FoamLayer;
}

/**
 * Вода: толща, плоскость и урез.
 *
 * - Толща (градиент глубины и каустика) — под актёрами.
 * - Плоскость (рябь, блики, макро-вариация, пена) — над толщей и под декором:
 *   это поверхность водоёма, и объект над ней её перекрывает.
 * - Урез лежит на земле у кромки, поэтому тоже уходит под декор: крона дерева
 *   на берегу не должна просвечивать через пену.
 *
 * Погружение объекта считается отдельно, по глубине под его нижней точкой.
 * UV у русла идут вдоль осевой, поэтому течение повторяет изгибы реки.
 */
export class SceneWaterService {
  private readonly depths = new SceneWaterDepthService();

  private readonly meshes = new SceneWaterMeshService();

  private readonly caustics = new SceneWaterCausticsShader();

  private readonly body = new SceneWaterBodyShader();

  private readonly surface = new SceneWaterSurfaceShader();

  private readonly riverBody = new SceneRiverBodyShader();

  private readonly riverSurface = new SceneRiverSurfaceShader();

  constructor(private readonly materials: SceneTerrainTextureService) {}

  /** Толща: градиент глубины и каустика по дну. */
  paintBody(
    layer: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number] = [0, 0],
  ): SceneWaterNode | null {
    if (isShaderRiver(element)) {
      return this.paintRiverBody(layer, element, resolution, zoom, endPads);
    }

    if (isShaderStill(element)) {
      return this.paintStillBody(layer, element, resolution, zoom, endPads);
    }

    const field = this.depths.field(element, resolution, endPads);
    const sprite = new Sprite(field.texture);
    sprite.position.set(field.bounds.x, field.bounds.y);
    sprite.width = field.bounds.width;
    sprite.height = field.bounds.height;
    layer.addChild(sprite);

    const geometry = this.geometry(element.shape, zoom, 0, endPads);
    if (geometry === null) {
      return null;
    }

    // На луже каустика глуше: вода стоячая и мутнее, а сетка узора на
    // маленьком пятне читается как лёд.
    const period = Math.max(1, resolution) * CAUSTICS_PERIOD_IPARI;
    const alpha = CAUSTICS_ALPHA * (geometry.along === undefined ? POND_CAUSTICS : 1);
    const node = this.causticsLayer(
      geometry,
      period,
      alpha,
      element,
      resolution,
      zoom,
      0,
      0,
      CAUSTICS_SPEED_IPARI * Math.max(1, resolution),
      0,
      SCENE_WATER_CAUSTICS.cellIpari,
    );
    if (node === null) {
      return null;
    }

    node.mesh.blendMode = 'add';
    layer.addChild(node.mesh);

    return { update: node.update };
  }

  /** Плоскость: рябь, блики, макро-вариация и пена; под декором и актёрами. */
  paintPlane(
    layer: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number] = [0, 0],
  ): SceneWaterNode | null {
    if (isShaderRiver(element)) {
      return this.paintRiverSurface(layer, element, resolution, zoom, endPads);
    }

    if (isShaderStill(element)) {
      return this.paintStillSurface(layer, element, resolution, zoom, endPads);
    }

    const geometry = this.geometry(element.shape, zoom, 0, endPads);
    const fallback = this.materials.texture('ripples');
    if (geometry === null || fallback === null) {
      return null;
    }

    const base = Math.max(1, resolution);
    const pond = geometry.along === undefined;
    const nodes: SceneWaterNode[] = [];
    for (const [index, spec] of PLANE_LAYERS.entries()) {
      const period = base * spec.periodIpari;
      const alpha = spec.alpha * this.lod(spec, period, zoom) * (pond ? POND_ALPHA : 1);
      if (alpha < 0.01) {
        continue;
      }

      const node =
        spec.material === 'caustics'
          ? this.causticsLayer(
              geometry,
              period,
              alpha,
              element,
              resolution,
              zoom,
              spec.angle,
              spec.skew,
              spec.speedIpari * base,
              index + 1,
              SCENE_WATER_CAUSTICS.cellIpari * (spec.periodIpari / CAUSTICS_PERIOD_IPARI),
            )
          : this.buildLayer(
              this.materials.texture(spec.material) ?? fallback,
              geometry,
              period,
              spec.angle,
              alpha,
              this.motionOf(element, geometry, period, spec.skew, spec.speedIpari * base),
            );
      if (node === null) {
        continue;
      }

      if (spec.additive === true) {
        node.mesh.blendMode = 'add';
      }

      layer.addChild(node.mesh);
      nodes.push({ update: node.update });
    }

    const macro = this.materials.texture('waterMacro');
    if (macro !== null) {
      const period = base * MACRO_IPARI;
      const node = this.buildLayer(
        macro,
        geometry,
        period,
        0,
        1,
        this.motionOf(element, geometry, period, 0, MACRO_SPEED_IPARI * base),
      );
      if (node !== null) {
        node.mesh.blendMode = 'multiply';
        layer.addChild(node.mesh);
        nodes.push({ update: node.update });
      }
    }

    if (nodes.length === 0) {
      return null;
    }

    return {
      update: (seconds: number): void => {
        for (const node of nodes) {
          node.update(seconds);
        }
      },
    };
  }

  /** Урез: пена, которая накатывает на берег и уносится течением. */
  paintFoam(
    layer: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number] = [0, 0],
  ): SceneWaterNode | null {
    const foam = this.materials.texture('foam');
    const outline = this.outline(element.shape, zoom, endPads);
    if (foam === null || outline === null) {
      return null;
    }

    const base = Math.max(1, resolution);
    const band = base * FOAM_IPARI;
    const period = band * (foam.width / Math.max(1, foam.height)) * FOAM_STRETCH;
    const patches = this.patchesOf(element.id);
    const toward = this.waterward(element, resolution, outline, endPads);
    const nodes: FoamNode[] = [];
    for (const spec of FOAM_LAYERS) {
      const width = band * spec.width;
      const strip = this.meshes.foamStrip(
        outline,
        width * FOAM_WATER_SHARE,
        width * (1 - FOAM_WATER_SHARE),
        period * spec.periodScale,
        patches,
        toward,
      );
      if (strip === null) {
        continue;
      }

      const positions = Float32Array.from(strip.positions);
      const uvs = Float32Array.from(strip.uvs);
      const mesh = new Mesh({
        geometry: new MeshGeometry({ positions, uvs, indices: strip.indices }),
        texture: foam,
      });
      mesh.alpha = spec.alpha;
      layer.addChild(mesh);
      nodes.push({
        mesh,
        positions,
        uvs,
        base: Float32Array.from(strip.uvs),
        strip,
        drift: (spec.speedIpari * base) / strip.period,
        offset: spec.offset,
        layer: spec,
      });
    }

    if (nodes.length === 0) {
      return null;
    }

    return {
      update: (seconds: number): void => {
        for (const node of nodes) {
          this.lapFoam(node, seconds);
        }
      },
    };
  }

  /** Глубина под точкой в единицах сцены; вне воды — ноль. */
  depthAt(
    element: SceneElement,
    resolution: number,
    point: ScenePoint,
    endPads: readonly [number, number] = [0, 0],
  ): number {
    return this.depths.depthAt(element, resolution, point, endPads);
  }

  /**
   * Тело реки на шейдере: галька сквозь воду, поглощение и каустика.
   *
   * Отдельной текстуры дна и спрайта глубины нет: дно семплится по мировым
   * координатам, поэтому на любом зуме это одна и та же галька, а не
   * растянутая картинка.
   */
  private paintStillBody(
    layer: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number],
  ): SceneWaterNode | null {
    const bed = this.materials.texture('bed');
    const sand = this.materials.texture('sand');
    const field = this.depths.field(element, resolution, endPads);
    // Тело и поверхность должны покрывать не только силуэт, но и запас под
    // кромку: у замкнутой воды это квад поля, у ленты — расширенный меш.
    const mesh = this.stillMesh(element, field, zoom, resolution, endPads);
    if (bed === null || sand === null || mesh === null) {
      return null;
    }

    const base = Math.max(1, resolution);
    // Ячейка задана в мире, как у текстуры: узор уходит вдаль и растёт при
    // приближении. Экранные границы только не дают ей стать мельче пикселя
    // (там узор гаснет по LOD) или крупнее пятна на максимальном зуме.
    const causticsCellIpari = Math.max(
      SCENE_WATER_CAUSTICS.minCellIpari,
      Math.min(SCENE_WATER_CAUSTICS.maxCellIpari, SCENE_WATER_CAUSTICS.cellIpari),
    );
    const worldCell = Math.max(1, base * causticsCellIpari);
    // Экранный зажим: узор не мельче minCellPx (иначе гаснет по LOD) и не
    // крупнее maxCellPx (иначе на максимальном зуме читается пятнами).
    const cellPixels = Math.max(
      SCENE_WATER_CAUSTICS.minCellPx,
      Math.min(SCENE_WATER_CAUSTICS.maxCellPx, zoom * worldCell),
    );
    const cellWorld = Math.max(1, cellPixels / Math.max(0.001, zoom));
    // Крупный слой «мути»: та же каустика, но крупнее, медленнее и прозрачнее.
    const coarseCellWorld = Math.max(1, cellWorld * SCENE_WATER_CAUSTICS.coarseShare);
    const coarsePixels = Math.max(1, zoom * coarseCellWorld);
    const coarseScale = 1 / coarseCellWorld;
    // Каустика сдвигается наклоном на долю ячейки: узор «плывёт» вместе
    // с волной, и преломление видно даже в глубокой середине.
    const causticsShift = cellWorld * SCENE_WATER_SHADER.causticsShiftShare;
    const period = this.materials.period('bed', resolution);
    const [offsetX, offsetY] = patternOffset(element.id, period);
    // Песок под водой — тот же тайл и тот же сдвиг, что у береговой полосы
    // (paintShore использует id + 17), поэтому на урезе рисунок продолжается.
    const sandPeriod = this.materials.period('sand', resolution);
    const [sandOffsetX, sandOffsetY] = patternOffset(element.id + 17, sandPeriod);
    const flow = SCENE_WATER_SHADER.noiseSpeedIpari * base;
    const noiseScale = 1 / (base * SCENE_WATER_SHADER.noiseIpari);
    const swellScale = 1 / (base * SCENE_WATER_SHADER.swellIpari);
    const rippleScale = 1 / (base * SCENE_WATER_SHADER.rippleIpari);
    const causticsScale = 1 / cellWorld;
    const node = this.body.create({
      field: field.data,
      fieldRect: [field.bounds.x, field.bounds.y, field.bounds.width, field.bounds.height],
      depthScale: field.scale,
      edgeScale: 1 / (base * SCENE_WATER_SHADER.edgeIpari),
      edgeSpeed: SCENE_WATER_SHADER.edgeSpeedIpari / SCENE_WATER_SHADER.edgeIpari,
      edgeAmp: base * SCENE_WATER_SHADER.edgeAmpIpari,
      edgeFineAmp: base * SCENE_WATER_SHADER.edgeFineAmpIpari,
      edgeSoft: base * SCENE_WATER_SHADER.edgeSoftIpari,
      washAmp: base * SCENE_WATER_SHADER.washAmpIpari,
      washRate: SCENE_WATER_SHADER.washRate * Math.PI * 2,
      washFreq: SCENE_WATER_SHADER.washFreq,
      wetWidth: base * SCENE_WATER_SHADER.wetWidthIpari,
      wetScale: 1 / (base * SCENE_WATER_SHADER.wetIpari),
      wetColor: SCENE_WATER_SHADER.wetColor,
      wetAlpha: SCENE_WATER_SHADER.wetAlpha,
      spillColor: SCENE_WATER_SHADER.spillColor,
      spillMix: SCENE_WATER_SHADER.spillMix,
      bed,
      bedPeriod: period,
      bedShift: [offsetX / period, offsetY / period],
      bedTint: SCENE_WATER_SHADER.bedTint,
      sand,
      sandPeriod,
      sandShift: [sandOffsetX / sandPeriod, sandOffsetY / sandPeriod],
      pebbleScale: 1 / (base * SCENE_WATER_SHADER.pebbleIpari),
      pebbleBase: SCENE_WATER_SHADER.pebbleBase,
      pebbleMix: SCENE_WATER_SHADER.pebbleMix,
      bedFade: base * SCENE_WATER_SHADER.bedFadeIpari,
      shallowColor: SCENE_WATER_SHADER.shallowColor,
      shallowMix: SCENE_WATER_SHADER.shallowMix,
      deepColor: SCENE_WATER_SHADER.deepColor,
      absorb: base * SCENE_WATER_SHADER.absorbIpari,
      refraction: base * SCENE_WATER_SHADER.refractionIpari,
      noiseScale,
      noiseStretch: SCENE_WATER_SHADER.noiseStretch,
      noiseSpeed: flow * noiseScale,
      lod: this.noiseLod(resolution, zoom, SCENE_WATER_SHADER.noiseIpari),
      swellScale,
      swellSpeed: SCENE_WATER_SHADER.noiseSpeedIpari * base * swellScale,
      swellLod: this.noiseLod(resolution, zoom, SCENE_WATER_SHADER.swellIpari),
      rippleScale,
      rippleSpeed: SCENE_WATER_SHADER.rippleSpeedIpari * base * rippleScale,
      rippleLod: this.noiseLod(resolution, zoom, SCENE_WATER_SHADER.rippleIpari),
      seed: this.seedOf(element.id),
      causticsScale,
      causticsLine: Math.max(0.06, Math.min(0.2, SCENE_WATER_CAUSTICS.linePx / cellPixels)),
      causticsAlpha: SCENE_WATER_SHADER.causticsAlpha,
      causticsLod: this.cellLod(zoom, cellWorld, SCENE_WATER_SHADER.causticsMinPx, SCENE_WATER_SHADER.causticsFullPx),
      causticsColor: SCENE_WATER_SHADER.causticsColor,
      causticsFlow: [CAUSTICS_SPEED_IPARI * base * causticsScale, 0],
      causticsShift,
      causticsScale2: coarseScale,
      causticsLine2: Math.max(0.06, Math.min(0.2, SCENE_WATER_CAUSTICS.coarseLinePx / coarsePixels)),
      causticsAlpha2: SCENE_WATER_SHADER.causticsAlpha * SCENE_WATER_CAUSTICS.coarseAlphaShare,
      causticsLod2: this.cellLod(
        zoom,
        coarseCellWorld,
        SCENE_WATER_SHADER.causticsMinPx,
        SCENE_WATER_SHADER.causticsFullPx,
      ),
      causticsFlow2: [
        CAUSTICS_SPEED_IPARI * SCENE_WATER_CAUSTICS.coarseSpeedShare * base * coarseScale,
        SCENE_WATER_CAUSTICS.coarseDriftIpari * base * coarseScale,
      ],
      causticsDetail: SCENE_WATER_SHADER.causticsDetail,
      causticsLens: SCENE_WATER_SHADER.causticsLens,
      causticsAngle2: SCENE_WATER_SHADER.causticsAngle2,
      deepMix: SCENE_WATER_SHADER.deepMix,
      poolScale: 1 / (base * SCENE_WATER_SHADER.poolIpari),
      poolMix: SCENE_WATER_SHADER.poolMix,
      toneScale: 1 / (base * SCENE_WATER_SHADER.toneIpari),
      toneSpeed: SCENE_WATER_SHADER.toneSpeed,
      toneMix: SCENE_WATER_SHADER.toneMix,
    });

    const waterMesh = new Mesh({
      geometry: new MeshGeometry(mesh),
      shader: node.shader,
    });
    layer.addChild(waterMesh);

    return { update: node.update };
  }

  /**
   * Тело реки: песок и галька, поглощение, слабая каустика.
   *
   * Дно семплится по мировым координатам, поэтому галька не растягивается.
   * Нормали волн двигают дно преломлением; наката на берег у реки нет.
   */
  private paintRiverBody(
    layer: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number],
  ): SceneWaterNode | null {
    const bed = this.materials.texture('bed');
    const sand = this.materials.texture('sand');
    const geometry = this.geometry(element.shape, zoom, resolution * SCENE_WATER_RIVER.marginIpari, endPads);
    if (bed === null || sand === null || geometry === null || geometry.along === undefined || geometry.across === undefined) {
      return null;
    }

    const field = this.depths.field(element, resolution, endPads);
    const base = Math.max(1, resolution);
    // Пятна живут в той же координате, что и маска, которую красили
    // красным на поверхности.
    const flickerScale = 0.6 / (base * SCENE_WATER_RIVER.sparkleIpari);
    const causticsCellIpari = Math.max(
      SCENE_WATER_CAUSTICS.minCellIpari,
      Math.min(SCENE_WATER_CAUSTICS.maxCellIpari, SCENE_WATER_CAUSTICS.cellIpari),
    );
    const worldCell = Math.max(1, base * causticsCellIpari);
    const cellPixels = Math.max(
      SCENE_WATER_CAUSTICS.minCellPx,
      Math.min(SCENE_WATER_CAUSTICS.maxCellPx, zoom * worldCell),
    );
    const cellWorld = Math.max(1, cellPixels / Math.max(0.001, zoom));
    const coarseCellWorld = Math.max(1, cellWorld * SCENE_WATER_CAUSTICS.coarseShare);
    const coarsePixels = Math.max(1, zoom * coarseCellWorld);
    const coarseScale = 1 / coarseCellWorld;
    const causticsShift = cellWorld * SCENE_WATER_RIVER.causticsShiftShare;
    const period = this.materials.period('bed', resolution);
    const [offsetX, offsetY] = patternOffset(element.id, period);
    const sandPeriod = this.materials.period('sand', resolution);
    const [sandOffsetX, sandOffsetY] = patternOffset(element.id + 17, sandPeriod);
    const waveOf = (
      periodIpari: number,
      speedIpari: number,
      angle: number,
      amp: number,
    ): [number, number, number, number] => {
      const scale = 1 / (base * periodIpari);

      return [scale, speedIpari / periodIpari, angle, amp];
    };
    const node = this.riverBody.create({
      field: field.data,
      fieldRect: [field.bounds.x, field.bounds.y, field.bounds.width, field.bounds.height],
      depthScale: field.scale,
      edgeScale: 1 / (base * SCENE_WATER_RIVER.edgeIpari),
      edgeSpeed: SCENE_WATER_RIVER.edgeSpeedIpari / SCENE_WATER_RIVER.edgeIpari,
      edgeAmp: base * SCENE_WATER_RIVER.edgeAmpIpari,
      edgeFineAmp: base * SCENE_WATER_RIVER.edgeFineAmpIpari,
      edgeSoft: base * SCENE_WATER_RIVER.edgeSoftIpari,
      wetWidth: base * SCENE_WATER_RIVER.wetWidthIpari,
      wetScale: 1 / (base * SCENE_WATER_RIVER.wetIpari),
      wetColor: SCENE_WATER_RIVER.wetColor,
      wetAlpha: SCENE_WATER_RIVER.wetAlpha,
      spillColor: SCENE_WATER_RIVER.spillColor,
      spillMix: SCENE_WATER_RIVER.spillMix,
      bed,
      bedPeriod: period,
      bedShift: [offsetX / period, offsetY / period],
      bedTint: SCENE_WATER_RIVER.bedTint,
      sand,
      sandPeriod,
      sandShift: [sandOffsetX / sandPeriod, sandOffsetY / sandPeriod],
      pebbleScale: 1 / (base * SCENE_WATER_RIVER.pebbleIpari),
      pebbleBase: SCENE_WATER_RIVER.pebbleBase,
      pebbleMix: SCENE_WATER_RIVER.pebbleMix,
      bedFade: base * SCENE_WATER_RIVER.bedFadeIpari,
      shallowColor: SCENE_WATER_RIVER.shallowColor,
      shallowMix: SCENE_WATER_RIVER.shallowMix,
      deepColor: SCENE_WATER_RIVER.deepColor,
      absorb: base * SCENE_WATER_RIVER.absorbIpari,
      shoal: base * 0.6,
      refraction: base * SCENE_WATER_RIVER.refractionIpari,
      flowBank: SCENE_WATER_RIVER.flowBank,
      flowCenter: SCENE_WATER_RIVER.flowCenter,
      waveA: waveOf(
        SCENE_WATER_RIVER.waveAIpari,
        SCENE_WATER_RIVER.waveASpeedIpari,
        SCENE_WATER_RIVER.waveAAngle,
        SCENE_WATER_RIVER.waveAAmp,
      ),
      waveB: waveOf(
        SCENE_WATER_RIVER.waveBIpari,
        SCENE_WATER_RIVER.waveBSpeedIpari,
        SCENE_WATER_RIVER.waveBAngle,
        SCENE_WATER_RIVER.waveBAmp,
      ),
      waveC: waveOf(
        SCENE_WATER_RIVER.waveCIpari,
        SCENE_WATER_RIVER.waveCSpeedIpari,
        SCENE_WATER_RIVER.waveCAngle,
        SCENE_WATER_RIVER.waveCAmp,
      ),
      waveEps: base * SCENE_WATER_RIVER.waveEpsIpari,
      waveBump: SCENE_WATER_RIVER.waveBump,
      waveShade: SCENE_WATER_RIVER.waveShade,
      waveLod: this.noiseLod(resolution, zoom, SCENE_WATER_RIVER.waveCIpari),
      noiseScaleA: 1 / (base * SCENE_WATER_RIVER.noiseAIpari),
      noiseSpeedA: SCENE_WATER_RIVER.noiseASpeedIpari / SCENE_WATER_RIVER.noiseAIpari,
      noiseStretchA: SCENE_WATER_RIVER.noiseAStretch,
      noiseWeightA: SCENE_WATER_RIVER.noiseAWeight,
      noiseScaleB: 1 / (base * SCENE_WATER_RIVER.noiseBIpari),
      noiseSpeedB: SCENE_WATER_RIVER.noiseBSpeedIpari / SCENE_WATER_RIVER.noiseBIpari,
      noiseStretchB: SCENE_WATER_RIVER.noiseBStretch,
      noiseWeightB: SCENE_WATER_RIVER.noiseBWeight,
      shadeScale: 1 / (base * SCENE_WATER_RIVER.shadeIpari),
      shadeSpeed: SCENE_WATER_RIVER.shadeSpeedIpari / SCENE_WATER_RIVER.shadeIpari,
      flickerScale,
      flickerSpeed: SCENE_WATER_RIVER.flickerSpeedIpari * flickerScale,
      flickerMix: SCENE_WATER_RIVER.flickerMix,
      seed: this.seedOf(element.id),
      causticsScale: 1 / cellWorld,
      causticsLine: Math.max(0.06, Math.min(0.2, SCENE_WATER_CAUSTICS.linePx / cellPixels)),
      causticsAlpha: SCENE_WATER_RIVER.causticsAlpha,
      causticsLod: this.cellLod(
        zoom,
        cellWorld,
        SCENE_WATER_RIVER.causticsMinPx,
        SCENE_WATER_RIVER.causticsFullPx,
      ),
      causticsColor: SCENE_WATER_RIVER.causticsColor,
      causticsFlow: [CAUSTICS_SPEED_IPARI * base * (1 / cellWorld), 0],
      causticsShift,
      causticsStretch: SCENE_WATER_RIVER.causticsStretch,
      causticsScale2: coarseScale,
      causticsLine2: Math.max(0.06, Math.min(0.2, SCENE_WATER_CAUSTICS.coarseLinePx / coarsePixels)),
      causticsAlpha2: SCENE_WATER_RIVER.causticsAlpha2,
      causticsLod2: this.cellLod(
        zoom,
        coarseCellWorld,
        SCENE_WATER_RIVER.causticsMinPx,
        SCENE_WATER_RIVER.causticsFullPx,
      ),
      causticsFlow2: [
        CAUSTICS_SPEED_IPARI * SCENE_WATER_CAUSTICS.coarseSpeedShare * base * coarseScale,
        SCENE_WATER_CAUSTICS.coarseDriftIpari * base * coarseScale,
      ],
      causticsAngle2: SCENE_WATER_SHADER.causticsAngle2,
      causticsDetail: SCENE_WATER_RIVER.causticsDetail,
      causticsLens: SCENE_WATER_RIVER.causticsLens,
      deepMix: SCENE_WATER_RIVER.deepMix,
      poolScale: 1 / (base * SCENE_WATER_RIVER.poolIpari),
      poolMix: SCENE_WATER_RIVER.poolMix,
      toneScale: 1 / (base * SCENE_WATER_RIVER.toneIpari),
      toneSpeed: SCENE_WATER_RIVER.toneSpeed,
      toneMix: SCENE_WATER_RIVER.toneMix,
    });

    const mesh = new Mesh({
      geometry: new MeshGeometry({
        positions: geometry.positions,
        uvs: this.meshes.channelUvs(geometry.along, geometry.across, 1, 0),
        indices: geometry.indices,
      }),
      shader: node.shader,
    });
    layer.addChild(mesh);

    return { update: node.update };
  }

  /**
   * Поверхность реки: нормали волн, солнечный блик и пена вдоль уреза.
   *
   * Меш шире силуэта на запас: кромке и пене нужно место за урезом, лишнее
   * отсекается полем глубины. Нормали считаются теми же волнами, что и
   * преломление дна в теле.
   */
  private paintRiverSurface(
    layer: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number],
  ): SceneWaterNode | null {
    const foam = this.materials.texture('foam');
    const geometry = this.geometry(element.shape, zoom, resolution * SCENE_WATER_RIVER.marginIpari, endPads);
    if (foam === null || geometry === null || geometry.along === undefined || geometry.across === undefined) {
      return null;
    }

    const field = this.depths.field(element, resolution, endPads);
    const base = Math.max(1, resolution);
    const waveOf = (
      periodIpari: number,
      speedIpari: number,
      angle: number,
      amp: number,
    ): [number, number, number, number] => {
      const scale = 1 / (base * periodIpari);

      return [scale, speedIpari / periodIpari, angle, amp];
    };
    const sparkleScale = 1 / (base * SCENE_WATER_RIVER.sparkleIpari);
    const foamPeriod = base * SCENE_WATER_RIVER.foamPeriodIpari;
    const foamMaskScale = 1 / (base * SCENE_WATER_RIVER.foamMaskIpari);
    const node = this.riverSurface.create({
      field: field.data,
      fieldRect: [field.bounds.x, field.bounds.y, field.bounds.width, field.bounds.height],
      depthScale: field.scale,
      edgeScale: 1 / (base * SCENE_WATER_RIVER.edgeIpari),
      edgeSpeed: SCENE_WATER_RIVER.edgeSpeedIpari / SCENE_WATER_RIVER.edgeIpari,
      edgeAmp: base * SCENE_WATER_RIVER.edgeAmpIpari,
      edgeFineAmp: base * SCENE_WATER_RIVER.edgeFineAmpIpari,
      edgeSoft: base * SCENE_WATER_RIVER.edgeSoftIpari,
      seed: this.seedOf(element.id),
      flowBank: SCENE_WATER_RIVER.flowBank,
      flowCenter: SCENE_WATER_RIVER.flowCenter,
      waveA: waveOf(
        SCENE_WATER_RIVER.waveAIpari,
        SCENE_WATER_RIVER.waveASpeedIpari,
        SCENE_WATER_RIVER.waveAAngle,
        SCENE_WATER_RIVER.waveAAmp,
      ),
      waveB: waveOf(
        SCENE_WATER_RIVER.waveBIpari,
        SCENE_WATER_RIVER.waveBSpeedIpari,
        SCENE_WATER_RIVER.waveBAngle,
        SCENE_WATER_RIVER.waveBAmp,
      ),
      waveC: waveOf(
        SCENE_WATER_RIVER.waveCIpari,
        SCENE_WATER_RIVER.waveCSpeedIpari,
        SCENE_WATER_RIVER.waveCAngle,
        SCENE_WATER_RIVER.waveCAmp,
      ),
      waveEps: base * SCENE_WATER_RIVER.waveEpsIpari,
      waveBump: SCENE_WATER_RIVER.waveBump,
      waveLod: this.noiseLod(resolution, zoom, SCENE_WATER_RIVER.waveCIpari),
      noiseScaleA: 1 / (base * SCENE_WATER_RIVER.noiseAIpari),
      noiseSpeedA: SCENE_WATER_RIVER.noiseASpeedIpari / SCENE_WATER_RIVER.noiseAIpari,
      noiseStretchA: SCENE_WATER_RIVER.noiseAStretch,
      noiseWeightA: SCENE_WATER_RIVER.noiseAWeight,
      noiseScaleB: 1 / (base * SCENE_WATER_RIVER.noiseBIpari),
      noiseSpeedB: SCENE_WATER_RIVER.noiseBSpeedIpari / SCENE_WATER_RIVER.noiseBIpari,
      noiseStretchB: SCENE_WATER_RIVER.noiseBStretch,
      noiseWeightB: SCENE_WATER_RIVER.noiseBWeight,
      sparkleScale,
      sparkleSpeed: SCENE_WATER_RIVER.sparkleSpeedIpari * sparkleScale,
      sparklePulse: SCENE_WATER_RIVER.sparklePulse,
      sparkleIntensity: SCENE_WATER_RIVER.sparkleIntensity,
      glintShine: SCENE_WATER_RIVER.glintShine,
      glintIntensity: SCENE_WATER_RIVER.glintIntensity,
      foam,
      foamPeriod,
      foamDrift: SCENE_WATER_RIVER.foamDriftIpari / SCENE_WATER_RIVER.foamPeriodIpari,
      foamMaskScale,
      foamMaskSpeed: SCENE_WATER_RIVER.foamMaskSpeedIpari * foamMaskScale,
      foamWidth: base * SCENE_WATER_RIVER.foamWidthIpari,
      foamAlpha: SCENE_WATER_RIVER.foamAlpha,
      foamLine: base * SCENE_WATER_RIVER.foamLineIpari,
      foamLineAlpha: SCENE_WATER_RIVER.foamLineAlpha,
      foamColor: SCENE_WATER_RIVER.foamColor,
      sun: SCENE_WATER_LIGHT.sun,
      sunColor: SCENE_WATER_LIGHT.sunColor,
      skyColor: SCENE_WATER_LIGHT.skyColor,
      skyIntensity: SCENE_WATER_RIVER.skyIntensity,
      baseAlpha: SCENE_WATER_RIVER.surfaceAlpha,
    });

    const mesh = new Mesh({
      geometry: new MeshGeometry({
        positions: geometry.positions,
        uvs: this.meshes.channelUvs(geometry.along, geometry.across, 1, 0),
        indices: geometry.indices,
      }),
      shader: node.shader,
    });
    layer.addChild(mesh);

    return { update: node.update };
  }

  /**
   * Поверхность стоячей воды на шейдере: нормали, преломление и блики.
   *
   * Меш шире силуэта на запас: кромке нужно место за урезом, а лишнее
   * отсекается полем глубины. Нормали считаются тем же шумом, что и в теле,
   * поэтому блики совпадают с дрожанием дна.
   */
  private paintStillSurface(
    layer: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number],
  ): SceneWaterNode | null {
    const field = this.depths.field(element, resolution, endPads);
    const mesh = this.stillMesh(element, field, zoom, resolution, endPads);
    if (mesh === null) {
      return null;
    }

    const base = Math.max(1, resolution);
    const noiseScale = 1 / (base * SCENE_WATER_SHADER.noiseIpari);
    const swellScale = 1 / (base * SCENE_WATER_SHADER.swellIpari);
    const glintCellIpari = Math.max(
      SCENE_WATER_LIGHT.glintMinIpari,
      Math.min(SCENE_WATER_LIGHT.glintMaxIpari, SCENE_WATER_LIGHT.glintPx / Math.max(0.001, zoom * base)),
    );
    const glintScale = 1 / Math.max(1, base * glintCellIpari);
    const rippleScale = 1 / (base * SCENE_WATER_SHADER.rippleIpari);
    const node = this.surface.create({
      field: field.data,
      fieldRect: [field.bounds.x, field.bounds.y, field.bounds.width, field.bounds.height],
      depthScale: field.scale,
      edgeScale: 1 / (base * SCENE_WATER_SHADER.edgeIpari),
      edgeSpeed: SCENE_WATER_SHADER.edgeSpeedIpari / SCENE_WATER_SHADER.edgeIpari,
      edgeAmp: base * SCENE_WATER_SHADER.edgeAmpIpari,
      edgeFineAmp: base * SCENE_WATER_SHADER.edgeFineAmpIpari,
      edgeSoft: base * SCENE_WATER_SHADER.edgeSoftIpari,
      washAmp: base * SCENE_WATER_SHADER.washAmpIpari,
      washRate: SCENE_WATER_SHADER.washRate * Math.PI * 2,
      washFreq: SCENE_WATER_SHADER.washFreq,
      seed: this.seedOf(element.id),
      noiseScale,
      noiseStretch: SCENE_WATER_SHADER.noiseStretch,
      noiseSpeed: SCENE_WATER_SHADER.noiseSpeedIpari * base * noiseScale,
      lod: this.noiseLod(resolution, zoom, SCENE_WATER_SHADER.noiseIpari),
      swellScale,
      swellSpeed: SCENE_WATER_SHADER.noiseSpeedIpari * base * swellScale,
      swellLod: this.noiseLod(resolution, zoom, SCENE_WATER_SHADER.swellIpari),
      rippleScale,
      rippleSpeed: SCENE_WATER_SHADER.rippleSpeedIpari * base * rippleScale,
      rippleLod: this.noiseLod(resolution, zoom, SCENE_WATER_SHADER.rippleIpari),
      rippleBump: SCENE_WATER_SHADER.rippleBump,
      bump: SCENE_WATER_SHADER.bump,
      glintScale,
      glintSpeed: SCENE_WATER_SHADER.noiseSpeedIpari * base * glintScale,
      foamScale: 1 / (base * SCENE_WATER_SHADER.foamIpari),
      foamSpeed: SCENE_WATER_SHADER.foamSpeedIpari / SCENE_WATER_SHADER.foamIpari,
      foamWidth: base * SCENE_WATER_SHADER.foamWidthIpari,
      foamAlpha: SCENE_WATER_SHADER.foamAlpha,
      foamLine: base * SCENE_WATER_SHADER.foamLineIpari,
      foamLineAlpha: SCENE_WATER_SHADER.foamLineAlpha,
      foamColor: SCENE_WATER_SHADER.foamColor,
      sun: SCENE_WATER_LIGHT.sun,
      sunColor: SCENE_WATER_LIGHT.sunColor,
      sheenShine: SCENE_WATER_LIGHT.sheenShine,
      sheenIntensity: SCENE_WATER_LIGHT.sheenIntensity,
      glintShine: SCENE_WATER_LIGHT.glintShine,
      glintIntensity: SCENE_WATER_LIGHT.glintIntensity,
      skyColor: SCENE_WATER_LIGHT.skyColor,
      skyIntensity: SCENE_WATER_LIGHT.skyIntensity,
      baseAlpha: SCENE_WATER_SHADER.surfaceAlpha,
    });

    const waterMesh = new Mesh({
      geometry: new MeshGeometry(mesh),
      shader: node.shader,
    });
    layer.addChild(waterMesh);

    return { update: node.update };
  }

  /** Множитель волн от размера тайла на экране: в отдалении рябь гаснет. */
  private noiseLod(resolution: number, zoom: number, periodIpari: number): number {
    return this.cellLod(zoom, periodIpari * Math.max(1, resolution), SCENE_WATER_SHADER.minTilePx, SCENE_WATER_SHADER.fullTilePx);
  }

  /** 0…1 по размеру клетки на экране: ниже `min` гаснет, выше `full` в силу. */
  private cellLod(zoom: number, cellWorld: number, min: number, full: number): number {
    const pixels = cellWorld * zoom;

    return Math.max(0, Math.min(1, (pixels - min) / Math.max(0.01, full - min)));
  }

  /** Сдвиг узоров от id: соседние реки не выглядят близнецами. */
  private seedOf(id: number): [number, number] {
    return [((id % 89) + 1) * 0.71, ((id % 53) + 1) * 1.13];
  }

  /** Освобождает закэшированные поля глубины. */
  destroy(): void {
    this.depths.destroy();
  }

  /**
   * Накат пены: кромка со стороны воды ходит к берегу и обратно, кромка на
   * берегу повторяет движение слабее.
   *
   * Фаза зависит от места вдоль контура, поэтому пятна наката вспыхивают в
   * разных местах в разное время, а не бегут одной волной по всему урезу.
   */
  private lapFoam(node: FoamNode, seconds: number): void {
    const { strip, layer, positions, uvs, base } = node;
    const count = strip.along.length;
    const drift = node.offset + node.drift * seconds;
    for (let index = 0; index < count; index += 1) {
      const lap = this.lap(strip.along[index], seconds, layer.lapRate);
      const water = strip.water[index] * (1 + layer.lap * lap);
      const land = strip.land[index] * (1 + layer.lap * 0.35 * lap);
      const centerX = strip.centers[index * 2];
      const centerY = strip.centers[index * 2 + 1];
      const axisX = strip.axes[index * 2];
      const axisY = strip.axes[index * 2 + 1];
      const vertex = index * 4;
      positions[vertex] = centerX - axisX * land;
      positions[vertex + 1] = centerY - axisY * land;
      positions[vertex + 2] = centerX + axisX * water;
      positions[vertex + 3] = centerY + axisY * water;
      uvs[index * 4] = base[index * 4] + drift;
      uvs[index * 4 + 2] = base[index * 4 + 2] + drift;
    }

    node.mesh.geometry.positions = positions;
    node.mesh.geometry.uvs = uvs;
  }

  /** Накат в точке контура: две волны разной частоты, диапазон −1…1. */
  private lap(along: number, seconds: number, rate: number): number {
    return (
      0.5 * Math.sin(TAU * rate * seconds + TAU * 2 * along) +
      0.5 * Math.sin(TAU * rate * 0.63 * seconds + TAU * 5 * along + 1.7)
    );
  }

  /**
   * Слой каустики: шейдер на реке, текстура — запасной путь и замкнутая вода.
   *
   * У замкнутой воды нет координаты вдоль русла, поэтому узор там остаётся
   * текстурным: шейдеру некуда привязать течение, а вихрь его и так прячет.
   */
  private causticsLayer(
    geometry: WaterGeometry,
    period: number,
    alpha: number,
    element: SceneElement,
    resolution: number,
    zoom: number,
    angle: number,
    skew: number,
    speed: number,
    seed: number,
    cellIpari: number,
  ): { mesh: Mesh<MeshGeometry, Shader>; update(seconds: number): void } | null {
    if (SCENE_WATER_CAUSTICS.shader && geometry.along !== undefined && geometry.across !== undefined) {
      return this.buildCaustics(geometry, period, alpha, element, resolution, zoom, skew, speed, seed, cellIpari);
    }

    const texture = this.materials.texture('caustics');
    if (texture === null) {
      return null;
    }

    return this.buildLayer(
      texture,
      geometry,
      period,
      angle,
      alpha,
      this.motionOf(element, geometry, period, skew, speed),
    );
  }

  /**
   * Меш процедурной каустики.
   *
   * UV — координаты русла, поэтому узор сам идёт вдоль реки: сдвиг во времени
   * внутри шейдера заменяет пересчёт UV на каждом кадре.
   */
  private buildCaustics(
    geometry: WaterGeometry,
    period: number,
    alpha: number,
    element: SceneElement,
    resolution: number,
    zoom: number,
    skew: number,
    speed: number,
    seed: number,
    cellIpari: number,
  ): { mesh: Mesh<MeshGeometry, Shader>; update(seconds: number): void } | null {
    const along = geometry.along;
    const across = geometry.across;
    if (along === undefined || across === undefined) {
      return null;
    }

    const cellWorld = cellIpari * Math.max(1, resolution);
    const cellsPerUv = period / cellWorld;
    const [driftX, driftY] = this.drift(element, period, skew, speed);
    // Ширина линии задана в пикселях экрана: в отдалении она шире ячейки, а
    // яркость шейдер гасит сам, поэтому сетка не мерцает.
    const line = Math.max(0.02, Math.min(0.6, SCENE_WATER_CAUSTICS.linePx / (zoom * cellWorld)));
    const layer = this.caustics.create({
      cellsPerUv,
      flow: [driftX * cellsPerUv, driftY * cellsPerUv],
      line,
      seed: (element.id % 97) * 1.37 + seed * 5.1,
    });

    const uvs = this.meshes.channelUvs(along, across, period, 0);
    const mesh = new Mesh({
      geometry: new MeshGeometry({ positions: geometry.positions, uvs, indices: geometry.indices }),
      shader: layer.shader,
    });
    mesh.alpha = alpha;

    return { mesh, update: layer.update };
  }

  /**
   * Меш слоя: базовые UV и движение — снос или вихрь.
   *
   * У ленты UV идут вдоль осевой, поэтому текстура не сползает с изгиба и не
   * накапливает сдвиг: движение вдоль русла — это сдвиг одной координаты.
   */
  private buildLayer(
    texture: Texture,
    geometry: WaterGeometry,
    period: number,
    angle: number,
    alpha: number,
    motion: WaterMotion,
  ): { mesh: Mesh; update(seconds: number): void } | null {
    const channel = geometry.along !== undefined && geometry.across !== undefined;
    let base: Float32Array;
    if (motion.kind === 'swirl') {
      const count = geometry.positions.length / 2;
      base = new Float32Array(count * 2);
      for (let index = 0; index < count; index += 1) {
        base[index * 2] = (geometry.positions[index * 2] - motion.center[0]) / period;
        base[index * 2 + 1] = (geometry.positions[index * 2 + 1] - motion.center[1]) / period;
      }
    } else if (channel) {
      base = this.meshes.channelUvs(geometry.along as Float32Array, geometry.across as Float32Array, period, angle);
    } else {
      base = this.meshes.worldUvs(geometry.positions, period, angle);
    }

    const uvs = Float32Array.from(base);
    const mesh = new Mesh({
      geometry: new MeshGeometry({ positions: geometry.positions, uvs, indices: geometry.indices }),
      texture,
    });
    mesh.alpha = alpha;

    if (motion.kind === 'swirl') {
      return {
        mesh,
        update: (seconds: number): void => {
          const spin = angle + motion.rate * seconds;
          const cos = Math.cos(spin);
          const sin = Math.sin(spin);
          for (let index = 0; index < uvs.length; index += 2) {
            const u = base[index];
            const v = base[index + 1];
            uvs[index] = u * cos - v * sin;
            uvs[index + 1] = u * sin + v * cos;
          }

          mesh.geometry.uvs = uvs;
        },
      };
    }

    return {
      mesh,
      update: (seconds: number): void => {
        // Снос задан в координатах русла, а UV повёрнуты на `angle`, поэтому
        // смещение доворачивается вместе с ними: узор едет вдоль течения, а не
        // по диагонали.
        const offsetX = motion.drift[0] * seconds;
        const offsetY = motion.drift[1] * seconds;
        const cos = Math.cos(angle);
        const sin = Math.sin(angle);
        const shiftX = offsetX * cos - offsetY * sin;
        const shiftY = offsetX * sin + offsetY * cos;
        for (let index = 0; index < uvs.length; index += 2) {
          uvs[index] = base[index] + shiftX;
          uvs[index + 1] = base[index + 1] + shiftY;
        }

        mesh.geometry.uvs = uvs;
      },
    };
  }

  /** Движение слоя: у ленты снос по руслу, у замкнутой воды — вихрь. */
  private motionOf(
    element: SceneElement,
    geometry: WaterGeometry,
    period: number,
    skew: number,
    speed: number,
  ): WaterMotion {
    if (geometry.along === undefined) {
      return { kind: 'swirl', center: this.centroid(geometry.positions), rate: SWIRL_RATE };
    }

    return { kind: 'flow', drift: this.drift(element, period, skew, speed) };
  }

  /**
   * Направление дрейфа в долях тайла за секунду.
   *
   * У ленты дрейф идёт вдоль русла (с небольшим поперечным скосом), у
   * замкнутого водоёма — по общему направлению течения в мировых координатах.
   */
  private drift(element: SceneElement, period: number, skew: number, speed: number): ScenePoint {
    const [flowX, flowY] = this.flowDirection(element);
    const cos = Math.cos(skew);
    const sin = Math.sin(skew);
    const isChannel = element.shape.type === 'path';
    const directionX = isChannel ? cos : flowX * cos - flowY * sin;
    const directionY = isChannel ? sin : flowX * sin + flowY * cos;

    return [(directionX * speed) / period, (directionY * speed) / period];
  }

  /** Центр водоёма: вокруг него крутится вихрь замкнутой воды. */
  private centroid(positions: Float32Array): ScenePoint {
    let x = 0;
    let y = 0;
    const count = Math.max(1, positions.length / 2);
    for (let index = 0; index < positions.length; index += 2) {
      x += positions[index];
      y += positions[index + 1];
    }

    return [x / count, y / count];
  }

  /** Множитель прозрачности от размера тайла на экране: в отдалении гаснет. */
  private lod(spec: WaterLayer, period: number, zoom: number): number {
    if (spec.fadeTile === undefined) {
      return 1;
    }

    const tile = period * zoom;

    return Math.max(0, Math.min(1, (tile - MIN_TILE_PX) / (spec.fadeTile - MIN_TILE_PX)));
  }

  /**
   * Ширина пены вдоль контура: пятна с пропусками.
   *
   * Три октавы дают неровность разных масштабов, а провал ниже порога гасит
   * пену совсем — урез не читается ровной лентой постоянной толщины.
   */
  private patchesOf(seed: number): (t: number) => number {
    const random = (value: number): number => {
      const chunk = Math.sin(value * 127.1) * 43758.5453;

      return chunk - Math.floor(chunk);
    };
    const phaseA = random(seed) * TAU;
    const phaseB = random(seed + 17) * TAU;
    const phaseC = random(seed + 31) * TAU;

    return (t: number): number => {
      const value =
        0.5 +
        0.3 * Math.sin(TAU * 3 * t + phaseA) +
        0.2 * Math.sin(TAU * 7 * t + phaseB) +
        0.12 * Math.sin(TAU * 15 * t + phaseC);
      const k = Math.max(0, Math.min(1, (value - 0.22) / 0.42));

      return k;
    };
  }

  /**
   * Куда от контура смотрит вода: пена должна лежать на воде, а не на песке.
   *
   * Нормаль контура не имеет выделенной стороны, поэтому сторону воды ищем по
   * полю глубины: снаружи водоёма глубина нулевая, внутри — растёт.
   */
  private waterward(
    element: SceneElement,
    resolution: number,
    outline: ScenePoint[],
    endPads: readonly [number, number],
  ): number {
    const step = Math.max(2, resolution * 0.08);
    const stride = Math.max(1, Math.round(outline.length / 12));
    let score = 0;
    for (let index = 0; index < outline.length; index += stride) {
      const previous = outline[(index + outline.length - 1) % outline.length];
      const next = outline[(index + 1) % outline.length];
      const dx = next[0] - previous[0];
      const dy = next[1] - previous[1];
      const length = Math.hypot(dx, dy);
      if (length === 0) {
        continue;
      }

      const normalX = dy / length;
      const normalY = -dx / length;
      const point = outline[index];
      const forward = this.depthAt(element, resolution, [point[0] + normalX * step, point[1] + normalY * step], endPads);
      const backward = this.depthAt(element, resolution, [point[0] - normalX * step, point[1] - normalY * step], endPads);
      score += Math.min(step * 4, forward) - Math.min(step * 4, backward);
    }

    return score >= 0 ? 1 : -1;
  }

  /** Среднее направление течения: для ленты — от начала к концу. */
  private flowDirection(element: SceneElement): ScenePoint {
    const shape = element.shape;
    if (shape.type !== 'path' || shape.points.length < 2) {
      return [0.85, 0.53];
    }

    const first = shape.points[0];
    const last = shape.points[shape.points.length - 1];
    const dx = last[0] - first[0];
    const dy = last[1] - first[1];
    const length = Math.hypot(dx, dy);
    if (length === 0) {
      return [0.85, 0.53];
    }

    return [dx / length, dy / length];
  }

  /**
   * Меш стоячей воды: квад поля с запасом или расширенная лента.
   *
   * У замкнутой воды нет русла, поэтому проще взять квад по границам поля:
   * он покрывает силуэт и запас под мокрую кромку, а лишнее отсекает поле
   * глубины.
   */
  private stillMesh(
    element: SceneElement,
    field: SceneWaterDepth,
    zoom: number,
    resolution: number,
    endPads: readonly [number, number],
  ): { positions: Float32Array; uvs: Float32Array; indices: Uint32Array } | null {
    if (element.shape.type === 'path') {
      const geometry = this.geometry(element.shape, zoom, resolution * SCENE_WATER_SHADER.marginIpari, endPads);
      if (geometry === null || geometry.along === undefined || geometry.across === undefined) {
        return null;
      }

      return {
        positions: geometry.positions,
        uvs: this.meshes.channelUvs(geometry.along, geometry.across, 1, 0),
        indices: geometry.indices,
      };
    }

    const bounds = field.bounds;
    const positions = new Float32Array([
      bounds.x,
      bounds.y,
      bounds.x + bounds.width,
      bounds.y,
      bounds.x + bounds.width,
      bounds.y + bounds.height,
      bounds.x,
      bounds.y + bounds.height,
    ]);

    return {
      positions,
      uvs: Float32Array.from(positions),
      indices: new Uint32Array([0, 1, 2, 0, 2, 3]),
    };
  }

  /** Геометрия силуэта: полоса ленты или триангулированный контур. */
  private geometry(
    shape: SceneShape,
    zoom: number,
    expand = 0,
    endPads: readonly [number, number] = [0, 0],
  ): WaterGeometry | null {
    if (shape.type === 'path') {
      return this.ribbonGeometry(shape, zoom, expand, endPads);
    }

    const contour = this.contour(shape, zoom);
    if (contour === null || contour.length < 3) {
      return null;
    }

    const flat = contour.flat();
    const triangles = earcut(flat);
    if (triangles.length < 3) {
      return null;
    }

    return { positions: new Float32Array(flat), indices: new Uint32Array(triangles) };
  }

  private ribbonGeometry(
    shape: Extract<SceneShape, { type: 'path' }>,
    zoom: number,
    expand = 0,
    endPads: readonly [number, number] = [0, 0],
  ): WaterGeometry | null {
    // Сетка воды не должна быть грубее поля глубины: иначе на поворотах
    // читаются грани квадов, а не плавная кромка.
    const tolerance = Math.min(sceneCurveService.toleranceFor(zoom), 0.5);
    const geometry = sceneRibbonService.geometry(
      shape.points,
      shape.handles ?? [],
      shape.widths,
      tolerance,
      0,
      endPads[0],
      endPads[1],
    );
    const count = geometry.center.length;
    if (count < 2) {
      return null;
    }

    const positions = new Float32Array(count * 4);
    const along = new Float32Array(count * 2);
    const across = new Float32Array(count * 2);
    const indices = new Uint32Array((count - 1) * 6);
    for (let index = 0; index < count; index += 1) {
      const left = geometry.left[index];
      const right = geometry.right[index];
      const center = geometry.center[index];
      const vertex = index * 2;
      const half = geometry.widths[index] / 2;
      // Нормаль наружу от осевой: по ней полоса расширяется на запас под накат.
      const outwardX = half === 0 ? 0 : (left[0] - center[0]) / half;
      const outwardY = half === 0 ? 0 : (left[1] - center[1]) / half;
      positions[vertex * 2] = left[0] + outwardX * expand;
      positions[vertex * 2 + 1] = left[1] + outwardY * expand;
      positions[vertex * 2 + 2] = right[0] - outwardX * expand;
      positions[vertex * 2 + 3] = right[1] - outwardY * expand;
      along[vertex] = geometry.lengths[index];
      along[vertex + 1] = geometry.lengths[index];
      across[vertex] = half + expand;
      across[vertex + 1] = -(half + expand);
      if (index < count - 1) {
        const triangle = index * 6;
        indices[triangle] = vertex;
        indices[triangle + 1] = vertex + 1;
        indices[triangle + 2] = vertex + 2;
        indices[triangle + 3] = vertex + 1;
        indices[triangle + 4] = vertex + 3;
        indices[triangle + 5] = vertex + 2;
      }
    }

    return { positions, indices, along, across };
  }

  /** Замкнутый контур фигуры: у ленты — левый берег и обратно правый. */
  private outline(
    shape: SceneShape,
    zoom: number,
    endPads: readonly [number, number] = [0, 0],
  ): ScenePoint[] | null {
    if (shape.type === 'path') {
      // Контур пены и берега тоже не должен быть грубее поля глубины.
      const tolerance = Math.min(sceneCurveService.toleranceFor(zoom), 0.5);
      const outline = sceneRibbonService.outline(
        shape.points,
        shape.handles ?? [],
        shape.widths,
        tolerance,
        0,
        endPads[0],
        endPads[1],
      );

      return outline.length >= 3 ? outline : null;
    }

    return this.contour(shape, zoom);
  }

  /** Контур замкнутой фигуры. */
  private contour(shape: SceneShape, zoom: number): ScenePoint[] | null {
    switch (shape.type) {
      case 'rect':
        return [
          [shape.x, shape.y],
          [shape.x + shape.w, shape.y],
          [shape.x + shape.w, shape.y + shape.h],
          [shape.x, shape.y + shape.h],
        ];
      case 'circle': {
        const points: ScenePoint[] = [];
        for (let index = 0; index < CIRCLE_SEGMENTS; index += 1) {
          const angle = (index / CIRCLE_SEGMENTS) * Math.PI * 2;
          points.push([shape.cx + Math.cos(angle) * shape.r, shape.cy + Math.sin(angle) * shape.r]);
        }

        return points;
      }
      case 'polygon':
        return sceneCurveService.flattenContour(
          shape.points,
          shape.handles ?? [],
          sceneCurveService.toleranceFor(zoom),
        );
      default:
        return null;
    }
  }
}
