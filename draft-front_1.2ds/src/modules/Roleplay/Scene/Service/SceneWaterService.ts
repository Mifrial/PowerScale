import { Mesh, MeshGeometry, Sprite, earcut } from 'pixi.js';
import type { Container, Texture } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
import { SceneWaterDepthService } from '@/modules/Roleplay/Scene/Service/SceneWaterDepthService';
import { SceneWaterMeshService } from '@/modules/Roleplay/Scene/Service/SceneWaterMeshService';
import type { WaterStrip } from '@/modules/Roleplay/Scene/Service/SceneWaterMeshService';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

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
  /** Аддитивное смешивание для бликов и каустики. */
  additive?: boolean;
  /** Размер тайла на экране, ниже которого слой гаснет, в пикселях. */
  fadeTile?: number;
}

/** Движение слоя: снос вдоль русла или вихрь вокруг центра. */
type WaterMotion = { kind: 'flow'; drift: ScenePoint } | { kind: 'swirl'; center: ScenePoint; rate: number };

/** Метрики берега: от них считаются ширины полос, пятен и волн. */
interface ShoreMetrics {
  outline: ScenePoint[];
  perimeter: number;
  count: number;
  /** Длина пути до каждой вершины; последний элемент — периметр. */
  cumulative: Float32Array;
  /** Половина локальной ширины воды для каждой вершины. */
  halfWidths: Float32Array;
  /** Характерная половина ширины водоёма. */
  halfWidth: number;
  maxDepth: number;
  area: number;
  /** Куда от контура смотрит вода: +1 или −1. */
  toward: number;
  /** Ширина «языка воды» на воду и на берег для каждой вершины. */
  edgeWater: Float32Array;
  edgeLand: Float32Array;
  foamCount: number;
  waveCount: number;
  waveSize: number;
  /** Сколько волн наката укладывается вдоль контура. */
  lapCycles: number;
  /** Сколько неровностей укладывается вдоль контура. */
  wobbleCycles: number;
  phase: number;
  closed: boolean;
}

/** Полоса уреза: вода, набегающая на берег, или плёнка на воде. */
interface EdgeNode {
  mesh: Mesh;
  positions: Float32Array;
  strip: WaterStrip;
  side: 'water' | 'land';
  rate: number;
  cycles: number;
  phase: number;
}

/** Клочок пены, живущий по полю наката. */
interface FoamPatchNode {
  sprite: Sprite;
  /** Станция на своём берегу; у замкнутой воды — на всём контуре. */
  station: number;
  /** 0 — левый берег, 1 — правый, −1 — замкнутая вода. */
  bank: number;
  scaleX: number;
  scaleY: number;
  alpha: number;
  threshold: number;
  /** Снос вдоль берега в единицах сцены в секунду. */
  drift: number;
}

/** Пятно ряби/блеска на открытой воде. */
interface WaveNode {
  sprite: Sprite;
  base: ScenePoint;
  direction: ScenePoint;
  period: number;
  phase: number;
  alpha: number;
  size: number;
  scaleX: number;
  scaleY: number;
}

/** Каустика по дну: подсвечивает, а не закрашивает. */
const BODY_CAUSTICS: WaterLayer[] = [
  { material: 'caustics', periodIpari: 1.7, angle: 0.12, alpha: 0.18, speedIpari: 0.08, skew: 0, additive: true },
  {
    material: 'caustics',
    periodIpari: 2.93,
    angle: -0.58,
    alpha: 0.1,
    speedIpari: 0.045,
    skew: 0.85,
    additive: true,
  },
];

/**
 * Каустика на поверхности.
 *
 * Раньше я её убрал, и вода стала читаться как ползущая текстура. Возвращаю
 * двумя несоизмеримыми масштабами и с малыми альфами: сетка не складывается в
 * плитку, а свет играет на воде.
 */
const SURFACE_CAUSTICS: WaterLayer[] = [
  {
    material: 'caustics',
    periodIpari: 2.37,
    angle: 0.36,
    alpha: 0.09,
    speedIpari: 0.055,
    skew: 0.25,
    additive: true,
    fadeTile: 110,
  },
  {
    material: 'caustics',
    periodIpari: 4.11,
    angle: -0.72,
    alpha: 0.055,
    speedIpari: 0.032,
    skew: -0.5,
    additive: true,
    fadeTile: 170,
  },
];

/** Скорость течения по умолчанию, ипари в секунду. */
const FLOW_SPEED_IPARI = 0.22;

/**
 * Слои плоскости воды: два масштаба ряби и блики.
 *
 * Периоды нарочно несоизмеримы: при кратных тайлах рисунок повторялся бы через
 * каждый тайл, а так сумма слоёв повторяется через десятки ипари. Второй слой
 * едет поперёк и чуть против течения — поверхность дрожит, а не едет слайдом.
 */
const PLANE_LAYERS: WaterLayer[] = [
  { material: 'ripples', periodIpari: 8.93, angle: 0, alpha: 0.28, speedIpari: FLOW_SPEED_IPARI * 0.7, skew: 0 },
  {
    material: 'ripples',
    periodIpari: 5.31,
    angle: Math.PI / 3.4,
    alpha: 0.16,
    speedIpari: FLOW_SPEED_IPARI * 0.38,
    skew: Math.PI * 0.65,
  },
  {
    material: 'glints',
    periodIpari: 4.13,
    angle: Math.PI / 7,
    alpha: 0.24,
    speedIpari: FLOW_SPEED_IPARI * 1.6,
    skew: -0.3,
    additive: true,
    fadeTile: 150,
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
const POND_ALPHA = 0.78;

/** Насколько глуше каустика у замкнутого водоёма. */
const POND_CAUSTICS = 0.65;

/** Доля локальной полуширины, уходящая на «язык воды». */
const EDGE_WATER_FRACTION = 0.32;

/** Защитные пределы языка воды в ипари. */
const EDGE_MIN_IPARI = 0.1;
const EDGE_MAX_IPARI = 0.7;

/** Во сколько раз плёнка на берегу уже водной части языка. */
const EDGE_LAND_RATIO = 0.55;

/** Защитные пределы плёнки на берегу в ипари. */
const EDGE_LAND_MIN_IPARI = 0.04;
const EDGE_LAND_MAX_IPARI = 0.4;

/** Альфа водной и береговой частей языка. */
const EDGE_WATER_ALPHA = 0.55;
const EDGE_LAND_ALPHA = 0.7;

/** Частота наката уреза, герц. */
const EDGE_LAP_RATE = 0.13;

/** Шаг между клочьями пены в ипари. */
const FOAM_SPACING_IPARI = 4.2;

/** Защитные пределы числа клочьев. */
const FOAM_MIN_COUNT = 6;
const FOAM_MAX_COUNT = 40;

/** Площадь воды на одно пятно ряби, ипари². */
const WAVE_AREA_IPARI2 = 14;

/** Защитные пределы числа пятен ряби. */
const WAVE_MIN_COUNT = 2;
const WAVE_MAX_COUNT = 12;

/** Разбиение окружности для воды-круга. */
const CIRCLE_SEGMENTS = 72;

/** Полный оборот в радианах. */
const TAU = Math.PI * 2;

const clamp = (value: number, min: number, max: number): number => Math.max(min, Math.min(max, value));

const smooth = (t: number): number => {
  const clamped = clamp(t, 0, 1);

  return clamped * clamped * (3 - 2 * clamped);
};

/**
 * Вода: толща, плоскость и урез.
 *
 * - Толща (градиент глубины и каустика) — под актёрами.
 * - Плоскость (рябь, каустика, блики, макро-вариация, пятна ряби) — над
 *   толщей и под декором: это поверхность водоёма, и объект над ней её
 *   перекрывает.
 * - Урез (язык воды и клочья пены) лежит на земле у кромки, поэтому тоже
 *   уходит под декор: крона дерева на берегу не должна просвечивать через пену.
 *
 * Погружение объекта считается отдельно, по глубине под его нижней точкой.
 * UV у русла идут вдоль осевой, поэтому течение повторяет изгибы реки.
 *
 * Ширины и количества здесь не заданы «на элемент»: они считаются от
 * локальной полуширины, периметра и площади, иначе узкий ручей и широкий плёс
 * получали бы одинаковые клочья пены и одинаковый накат.
 */
export class SceneWaterService {
  private readonly depths = new SceneWaterDepthService();

  private readonly meshes = new SceneWaterMeshService();

  private readonly metrics = new Map<number, { key: string; value: ShoreMetrics }>();

  constructor(private readonly materials: SceneTerrainTextureService) {}

  /** Толща: градиент глубины и каустика по дну. */
  paintBody(layer: Container, element: SceneElement, resolution: number, zoom: number): SceneWaterNode | null {
    const field = this.depths.field(element, resolution);
    const sprite = new Sprite(field.texture);
    sprite.position.set(field.bounds.x, field.bounds.y);
    sprite.width = field.bounds.width;
    sprite.height = field.bounds.height;
    layer.addChild(sprite);

    const caustics = this.materials.texture('caustics');
    if (caustics === null) {
      return null;
    }

    const geometry = this.geometry(element.shape, zoom);
    if (geometry === null) {
      return null;
    }

    const base = Math.max(1, resolution);
    const pond = geometry.along === undefined;
    const nodes: SceneWaterNode[] = [];
    for (const spec of BODY_CAUSTICS) {
      const period = base * spec.periodIpari;
      const alpha = spec.alpha * (pond ? POND_CAUSTICS : 1);
      const node = this.buildLayer(
        caustics,
        geometry,
        period,
        spec.angle,
        alpha,
        this.motionOf(element, geometry, period, spec.skew, spec.speedIpari * base),
      );
      if (node === null) {
        continue;
      }

      node.mesh.blendMode = 'add';
      layer.addChild(node.mesh);
      nodes.push({ update: node.update });
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

  /** Плоскость: рябь, каустика, блики, макро-вариация и пятна ряби. */
  paintPlane(layer: Container, element: SceneElement, resolution: number, zoom: number): SceneWaterNode | null {
    const geometry = this.geometry(element.shape, zoom);
    const fallback = this.materials.texture('ripples');
    if (geometry === null || fallback === null) {
      return null;
    }

    const base = Math.max(1, resolution);
    const pond = geometry.along === undefined;
    const nodes: SceneWaterNode[] = [];
    const addLayer = (spec: WaterLayer): void => {
      const texture = this.materials.texture(spec.material) ?? fallback;
      const period = base * spec.periodIpari;
      const alpha = spec.alpha * this.lod(spec, period, zoom) * (pond ? POND_ALPHA : 1);
      if (alpha < 0.01) {
        return;
      }

      const node = this.buildLayer(
        texture,
        geometry,
        period,
        spec.angle,
        alpha,
        this.motionOf(element, geometry, period, spec.skew, spec.speedIpari * base),
      );
      if (node === null) {
        return;
      }

      if (spec.additive === true) {
        node.mesh.blendMode = 'add';
      }

      layer.addChild(node.mesh);
      nodes.push({ update: node.update });
    };

    for (const spec of PLANE_LAYERS) {
      addLayer(spec);
    }

    for (const spec of SURFACE_CAUSTICS) {
      addLayer(spec);
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

    const sheen = this.materials.texture('sheen');
    const metrics = this.metricsOf(element, resolution, zoom);
    const waves =
      sheen !== null && metrics !== null ? this.createWaves(layer, metrics, sheen, element, resolution) : [];

    if (nodes.length === 0 && waves.length === 0) {
      return null;
    }

    return {
      update: (seconds: number): void => {
        for (const node of nodes) {
          node.update(seconds);
        }

        for (const wave of waves) {
          this.updateWave(wave, seconds);
        }
      },
    };
  }

  /** Урез: язык воды, который накатывает на берег, и клочья пены. */
  paintFoam(layer: Container, element: SceneElement, resolution: number, zoom: number): SceneWaterNode | null {
    const metrics = this.metricsOf(element, resolution, zoom);
    if (metrics === null) {
      return null;
    }

    const edgeTexture = this.materials.texture('edge');
    const foamTexture = this.materials.texture('foamPatch');
    const nodes: SceneWaterNode[] = [];
    if (edgeTexture !== null) {
      const period = this.materials.period('edge', resolution);
      const wobble = this.edgeWobble(metrics);
      const rate = EDGE_LAP_RATE;
      const strips: { side: 'water' | 'land'; mesh: Mesh; strip: WaterStrip }[] = [];
      const waterStrip = this.meshes.foamStrip(metrics.outline, 0, 0, period, wobble, metrics.toward, {
        waterHalfs: metrics.edgeWater,
      });
      if (waterStrip !== null) {
        strips.push({
          side: 'water',
          mesh: this.stripMesh(waterStrip, edgeTexture, EDGE_WATER_ALPHA),
          strip: waterStrip,
        });
      }

      const landStrip = this.meshes.foamStrip(metrics.outline, 0, 0, period, wobble, metrics.toward, {
        landHalfs: metrics.edgeLand,
        flipV: true,
      });
      if (landStrip !== null) {
        strips.push({ side: 'land', mesh: this.stripMesh(landStrip, edgeTexture, EDGE_LAND_ALPHA), strip: landStrip });
      }

      for (const { side, mesh, strip } of strips) {
        layer.addChild(mesh);
        const node: EdgeNode = {
          mesh,
          positions: Float32Array.from(strip.positions),
          strip,
          side,
          rate,
          cycles: metrics.lapCycles,
          phase: metrics.phase,
        };
        nodes.push({ update: (seconds: number): void => this.updateEdge(node, seconds) });
      }
    }

    const patches = foamTexture === null ? [] : this.createFoam(layer, metrics, foamTexture, element, resolution);
    if (patches.length > 0) {
      nodes.push({
        update: (seconds: number): void => {
          for (const patch of patches) {
            this.updateFoam(patch, metrics, seconds);
          }
        },
      });
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

  /** Глубина под точкой в единицах сцены; вне воды — ноль. */
  depthAt(element: SceneElement, resolution: number, point: ScenePoint): number {
    return this.depths.depthAt(element, resolution, point);
  }

  /** Освобождает закэшированные поля глубины. */
  destroy(): void {
    this.depths.destroy();
    this.metrics.clear();
  }

  /** Меш полосы уреза с общей текстурой. */
  private stripMesh(strip: WaterStrip, texture: Texture, alpha: number): Mesh {
    const mesh = new Mesh({
      geometry: new MeshGeometry({
        positions: Float32Array.from(strip.positions),
        uvs: Float32Array.from(strip.uvs),
        indices: strip.indices,
      }),
      texture,
    });
    mesh.alpha = alpha;

    return mesh;
  }

  /**
   * Накат уреза: язык воды ходит к берегу и обратно.
   *
   * Водная часть меняется слабо, береговая — сильно: вода то накрывает песок
   * тонкой плёнкой, то отходит. Фаза зависит от места вдоль контура, поэтому
   * язык вспыхивает в разных местах, а не дышит весь урез сразу.
   */
  private updateEdge(node: EdgeNode, seconds: number): void {
    const { strip, positions, side, rate, cycles, phase } = node;
    const count = strip.along.length;
    for (let index = 0; index < count; index += 1) {
      const lap = this.lap(strip.along[index], seconds, rate, cycles, phase);
      const wave = 0.5 + 0.5 * lap;
      const scale = side === 'land' ? 0.2 + 0.8 * wave : 0.8 + 0.2 * wave;
      const width = (side === 'land' ? strip.land[index] : strip.water[index]) * scale;
      const centerX = strip.centers[index * 2];
      const centerY = strip.centers[index * 2 + 1];
      const axisX = strip.axes[index * 2];
      const axisY = strip.axes[index * 2 + 1];
      const vertex = index * 4;
      if (side === 'land') {
        positions[vertex] = centerX - axisX * width;
        positions[vertex + 1] = centerY - axisY * width;
        positions[vertex + 2] = centerX;
        positions[vertex + 3] = centerY;
      } else {
        positions[vertex] = centerX;
        positions[vertex + 1] = centerY;
        positions[vertex + 2] = centerX + axisX * width;
        positions[vertex + 3] = centerY + axisY * width;
      }
    }

    node.mesh.geometry.positions = positions;
  }

  /**
   * Клочья пены вдоль берега.
   *
   * Каждое живёт по полю наката: накатило — проявилось и наехало на берег,
   * откатило — растворилось. Вдоль берега клочья едут вниз по течению, для
   * чего левый и правый берега обновляются независимо: у замкнутого контура
   * UV-петля шла бы на одном берегу против течения.
   */
  private createFoam(
    layer: Container,
    metrics: ShoreMetrics,
    texture: Texture,
    element: SceneElement,
    resolution: number,
  ): FoamPatchNode[] {
    const random = this.randomOf(Math.abs(Math.round(element.id)) + 31);
    const base = Math.max(1, resolution);
    const patches: FoamPatchNode[] = [];
    const halfCount = Math.ceil(metrics.foamCount / 2);
    for (let index = 0; index < metrics.foamCount; index += 1) {
      const closed = metrics.closed;
      const bank = closed ? -1 : index < halfCount ? 0 : 1;
      let station: number;
      if (closed) {
        station = (index + random() * 0.8) / metrics.foamCount;
      } else if (bank === 0) {
        station = ((index + random() * 0.7) / halfCount) * 0.5;
      } else {
        station = 0.5 + ((index - halfCount + random() * 0.7) / Math.max(1, metrics.foamCount - halfCount)) * 0.5;
      }

      const slot = Math.min(metrics.count - 1, Math.max(0, Math.round(station * metrics.count)));
      const edgeWater = metrics.edgeWater[slot];
      const edgeLand = metrics.edgeLand[slot];
      const sizeAlong = edgeWater * (1.4 + random() * 1.4) + edgeLand * 0.8;
      const sizeAcross = edgeWater * (0.8 + random() * 0.8) + edgeLand * 0.3;
      const sprite = new Sprite(texture);
      sprite.anchor.set(0.5);
      sprite.tint = 0xf4fbff;
      layer.addChild(sprite);
      patches.push({
        sprite,
        station,
        bank,
        scaleX: sizeAlong / Math.max(1, texture.width),
        scaleY: sizeAcross / Math.max(1, texture.height),
        alpha: 0.45 + random() * 0.27,
        threshold: 0.1 + random() * 0.4,
        drift: (0.018 + random() * 0.04) * base,
      });
    }

    return patches;
  }

  /** Движение клочья: станция на своём берегу, накат и растворение. */
  private updateFoam(patch: FoamPatchNode, metrics: ShoreMetrics, seconds: number): void {
    const half = 0.5;
    let station: number;
    let edge = 1;
    if (patch.bank < 0) {
      station = (((patch.station + (patch.drift * seconds) / metrics.perimeter) % 1) + 1) % 1;
    } else {
      const travel = (patch.drift * seconds) / metrics.perimeter;
      const direction = patch.bank === 0 ? 1 : -1;
      if (patch.bank === 0) {
        station = (((patch.station + direction * travel) % half) + half) % half;
        edge = Math.min(station, half - station) / half;
      } else {
        const local = (((patch.station - half + direction * travel) % half) + half) % half;
        station = half + local;
        edge = Math.min(local, half - local) / half;
      }

      // У торцов реки клочья гаснут, чтобы не перескакивать с берега на берег.
      edge = smooth(edge / 0.06);
    }

    const lap = this.lap(station, seconds, EDGE_LAP_RATE, metrics.lapCycles, metrics.phase);
    const wave = Math.max(0, 0.5 + 0.5 * lap);
    const alpha = patch.alpha * smooth((wave - patch.threshold) / 0.35) * (patch.bank < 0 ? 1 : edge);
    patch.sprite.visible = alpha > 0.01;
    if (!patch.sprite.visible) {
      return;
    }

    const point = this.pointAt(metrics, station);
    const tangent = this.tangentAt(metrics, station);
    const slot = Math.min(metrics.count - 1, Math.max(0, Math.round(station * metrics.count) % metrics.count));
    const edgeWater = metrics.edgeWater[slot];
    const edgeLand = metrics.edgeLand[slot];
    const offset = edgeWater * 0.1 - edgeLand * (0.15 + 0.65 * wave);
    const normalX = tangent[1] * metrics.toward;
    const normalY = -tangent[0] * metrics.toward;
    const factor = 0.7 + 0.5 * wave;
    patch.sprite.position.set(point[0] + normalX * offset, point[1] + normalY * offset);
    patch.sprite.rotation = Math.atan2(tangent[1], tangent[0]);
    patch.sprite.scale.set(patch.scaleX * factor, patch.scaleY * factor);
    patch.sprite.alpha = alpha;
  }

  /** Пятна ряби на открытой воде: появляются и гаснут в разных местах. */
  private createWaves(
    layer: Container,
    metrics: ShoreMetrics,
    texture: Texture,
    element: SceneElement,
    resolution: number,
  ): WaveNode[] {
    const field = this.depths.field(element, resolution);
    const random = this.randomOf(Math.abs(Math.round(element.id)) + 71);
    const threshold = Math.max(field.cell * 0.5, metrics.maxDepth * 0.48);
    const step = Math.max(1, Math.round(Math.sqrt((field.cols * field.rows) / 800)));
    const spots: ScenePoint[] = [];
    for (let row = 0; row < field.rows; row += step) {
      for (let column = 0; column < field.cols; column += step) {
        if (field.depth[row * field.cols + column] > threshold) {
          spots.push([field.bounds.x + (column + 0.5) * field.cell, field.bounds.y + (row + 0.5) * field.cell]);
        }
      }
    }

    if (spots.length === 0) {
      return [];
    }

    const direction = this.flowDirection(element);
    const waves: WaveNode[] = [];
    for (let index = 0; index < metrics.waveCount; index += 1) {
      const spot = spots[Math.min(spots.length - 1, Math.floor(random() * spots.length))];
      const size = metrics.waveSize * (0.7 + random() * 0.6);
      const sprite = new Sprite(texture);
      sprite.anchor.set(0.5);
      sprite.blendMode = 'add';
      sprite.rotation = Math.atan2(direction[1], direction[0]);
      const scaleX = size / Math.max(1, texture.width);
      const scaleY = (size * (0.32 + random() * 0.28)) / Math.max(1, texture.height);
      sprite.scale.set(scaleX, scaleY);
      layer.addChild(sprite);
      waves.push({
        sprite,
        base: spot,
        direction,
        period: 5 + random() * 5,
        phase: random() * 5,
        alpha: 0.1 + random() * 0.12,
        size,
        scaleX,
        scaleY,
      });
    }

    return waves;
  }

  private updateWave(node: WaveNode, seconds: number): void {
    const cycle = ((((seconds + node.phase) % node.period) + node.period) % node.period) / node.period;
    const envelope = Math.sin(Math.PI * cycle) ** 2;
    const travel = (cycle - 0.5) * node.size;
    node.sprite.position.set(node.base[0] + node.direction[0] * travel, node.base[1] + node.direction[1] * travel);
    node.sprite.alpha = node.alpha * envelope;
    node.sprite.visible = envelope > 0.02;
    node.sprite.scale.set(node.scaleX * (0.9 + 0.1 * envelope), node.scaleY * (0.9 + 0.1 * envelope));
  }

  /** Накат в точке контура: две волны разной частоты, диапазон −1…1. */
  private lap(t: number, seconds: number, rate: number, cycles: number, phase: number): number {
    return (
      0.55 * Math.sin(TAU * cycles * t + TAU * rate * seconds + phase) +
      0.45 * Math.sin(TAU * cycles * 2.17 * t - TAU * rate * 0.71 * seconds + phase * 1.7 + 1.1)
    );
  }

  /** Неровность уреза вдоль контура: ширина гуляет, но не исчезает. */
  private edgeWobble(metrics: ShoreMetrics): (t: number) => number {
    const phase = metrics.phase;

    return (t: number): number =>
      0.58 +
      0.24 * Math.sin(TAU * metrics.wobbleCycles * t + phase) +
      0.18 * Math.sin(TAU * metrics.wobbleCycles * 2.31 * t + phase * 1.7);
  }

  /** Точка на контуре по доле пути. */
  private pointAt(metrics: ShoreMetrics, t: number): ScenePoint {
    const index = this.segmentAt(metrics, t);
    const start = metrics.outline[index];
    const end = metrics.outline[(index + 1) % metrics.count];
    const length = metrics.cumulative[index + 1] - metrics.cumulative[index];
    const s = (((t % 1) + 1) % 1) * metrics.perimeter;
    const k = length > 0 ? (s - metrics.cumulative[index]) / length : 0;

    return [start[0] + (end[0] - start[0]) * k, start[1] + (end[1] - start[1]) * k];
  }

  /** Единичная касательная к контуру по доле пути. */
  private tangentAt(metrics: ShoreMetrics, t: number): ScenePoint {
    const index = this.segmentAt(metrics, t);
    const start = metrics.outline[index];
    const end = metrics.outline[(index + 1) % metrics.count];
    const dx = end[0] - start[0];
    const dy = end[1] - start[1];
    const length = Math.hypot(dx, dy);
    if (length === 0) {
      return [1, 0];
    }

    return [dx / length, dy / length];
  }

  /** Номер сегмента контура по доле пути. */
  private segmentAt(metrics: ShoreMetrics, t: number): number {
    const total = metrics.perimeter;
    if (total <= 0) {
      return 0;
    }

    const s = (((t % 1) + 1) % 1) * total;
    let index = 0;
    while (index < metrics.count - 1 && metrics.cumulative[index + 1] <= s) {
      index += 1;
    }

    return index;
  }

  /**
   * Метрики берега: периметр, локальная ширина, площадь и производные размеры.
   * Кэш по фигуре и разрешению: пересчёт идёт только при смене геометрии.
   */
  private metricsOf(element: SceneElement, resolution: number, zoom: number): ShoreMetrics | null {
    const key = `${Math.max(1, resolution)}|${sceneCurveService.toleranceFor(zoom)}|${JSON.stringify(element.shape)}`;
    const cached = this.metrics.get(element.id);
    if (cached !== undefined && cached.key === key) {
      return cached.value;
    }

    const outline = this.outline(element.shape, zoom);
    if (outline === null) {
      return null;
    }

    const count = outline.length;
    const cumulative = new Float32Array(count + 1);
    for (let index = 0; index < count; index += 1) {
      const start = outline[index];
      const end = outline[(index + 1) % count];
      cumulative[index + 1] = cumulative[index] + Math.hypot(end[0] - start[0], end[1] - start[1]);
    }

    const perimeter = cumulative[count];
    const field = this.depths.field(element, resolution);
    let maxDepth = 0;
    let wetCells = 0;
    for (let index = 0; index < field.depth.length; index += 1) {
      const value = field.depth[index];
      if (value > 0) {
        wetCells += 1;
        maxDepth = Math.max(maxDepth, value);
      }
    }

    const area = wetCells * field.cell * field.cell;
    const base = Math.max(1, resolution);
    const halfWidths = new Float32Array(count);
    let halfWidth = Math.max(1, maxDepth);
    if (element.shape.type === 'path') {
      const ribbon = sceneRibbonService.geometry(
        element.shape.points,
        element.shape.handles ?? [],
        element.shape.widths,
        sceneCurveService.toleranceFor(zoom),
      );
      const halfCount = ribbon.widths.length;
      let sum = 0;
      for (let index = 0; index < count; index += 1) {
        const center =
          index < count / 2
            ? Math.round((index / Math.max(1, count / 2)) * Math.max(0, halfCount - 1))
            : Math.round(((count - 1 - index) / Math.max(1, count / 2)) * Math.max(0, halfCount - 1));
        const width = ribbon.widths[Math.min(halfCount - 1, Math.max(0, center))] ?? 1;
        const half = Math.max(1, width) / 2;
        halfWidths[index] = half;
        sum += half;
      }

      halfWidth = Math.max(1, sum / count);
    } else {
      halfWidths.fill(halfWidth);
    }

    const edgeWater = new Float32Array(count);
    const edgeLand = new Float32Array(count);
    let edgeSum = 0;
    for (let index = 0; index < count; index += 1) {
      const water = clamp(halfWidths[index] * EDGE_WATER_FRACTION, EDGE_MIN_IPARI * base, EDGE_MAX_IPARI * base);
      edgeWater[index] = water;
      edgeLand[index] = clamp(water * EDGE_LAND_RATIO, EDGE_LAND_MIN_IPARI * base, EDGE_LAND_MAX_IPARI * base);
      edgeSum += water;
    }

    const medianEdge = edgeSum / Math.max(1, count);
    const spacing = Math.max(FOAM_SPACING_IPARI * base, 4 * medianEdge);
    const foamCount = clamp(Math.ceil(perimeter / Math.max(1, spacing)), FOAM_MIN_COUNT, FOAM_MAX_COUNT);
    const waveCount = clamp(Math.round(area / (WAVE_AREA_IPARI2 * base * base)), WAVE_MIN_COUNT, WAVE_MAX_COUNT);
    const width = Math.max(1, halfWidth * 2);
    const lapCycles = clamp(perimeter / clamp(width * 5, 6 * base, 20 * base), 3, 24);
    const wobbleCycles = clamp(perimeter / Math.max(1, 3 * base), 4, 40);
    const random = this.randomOf(Math.abs(Math.round(element.id)) + 7);
    const value: ShoreMetrics = {
      outline,
      perimeter,
      count,
      cumulative,
      halfWidths,
      halfWidth,
      maxDepth,
      area,
      toward: this.waterward(element, resolution, outline),
      edgeWater,
      edgeLand,
      foamCount,
      waveCount,
      waveSize: clamp(halfWidth * 2.4, 1.2 * base, 6 * base),
      lapCycles,
      wobbleCycles,
      phase: random() * TAU,
      closed: element.shape.type !== 'path',
    };
    this.metrics.set(element.id, { key, value });

    return value;
  }

  /** Детерминированный генератор: пятна не прыгают между перерисовками. */
  private randomOf(seed: number): () => number {
    let state = Math.max(1, Math.floor(seed)) >>> 0;

    return (): number => {
      state = (state * 1664525 + 1013904223) >>> 0;

      return state / 4294967296;
    };
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
        const offsetX = motion.drift[0] * seconds;
        const offsetY = motion.drift[1] * seconds;
        for (let index = 0; index < uvs.length; index += 2) {
          uvs[index] = base[index] + offsetX;
          uvs[index + 1] = base[index + 1] + offsetY;
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
   * Куда от контура смотрит вода: пена должна лежать на воде, а не на песке.
   *
   * Нормаль контура не имеет выделенной стороны, поэтому сторону воды ищем по
   * полю глубины: снаружи водоёма глубина нулевая, внутри — растёт.
   */
  private waterward(element: SceneElement, resolution: number, outline: ScenePoint[]): number {
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
      const forward = this.depthAt(element, resolution, [point[0] + normalX * step, point[1] + normalY * step]);
      const backward = this.depthAt(element, resolution, [point[0] - normalX * step, point[1] - normalY * step]);
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

  /** Геометрия силуэта: полоса ленты или триангулированный контур. */
  private geometry(shape: SceneShape, zoom: number): WaterGeometry | null {
    if (shape.type === 'path') {
      return this.ribbonGeometry(shape, zoom);
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

  private ribbonGeometry(shape: Extract<SceneShape, { type: 'path' }>, zoom: number): WaterGeometry | null {
    const geometry = sceneRibbonService.geometry(
      shape.points,
      shape.handles ?? [],
      shape.widths,
      sceneCurveService.toleranceFor(zoom),
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
      const vertex = index * 2;
      const half = geometry.widths[index] / 2;
      positions[vertex * 2] = left[0];
      positions[vertex * 2 + 1] = left[1];
      positions[vertex * 2 + 2] = right[0];
      positions[vertex * 2 + 3] = right[1];
      along[vertex] = geometry.lengths[index];
      along[vertex + 1] = geometry.lengths[index];
      across[vertex] = half;
      across[vertex + 1] = -half;
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
  private outline(shape: SceneShape, zoom: number): ScenePoint[] | null {
    if (shape.type === 'path') {
      const outline = sceneRibbonService.outline(
        shape.points,
        shape.handles ?? [],
        shape.widths,
        sceneCurveService.toleranceFor(zoom),
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
