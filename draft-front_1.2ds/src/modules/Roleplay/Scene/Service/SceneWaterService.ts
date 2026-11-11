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

/**
 * Каустика по дну: два несоизмеримых масштаба.
 *
 * Один тайл 1.7 ипари на широкой реке повторяется квадратной сеткой. Второй
 * слой другого масштаба и под другим углом разбивает эту сетку, поэтому узор
 * читается как игра света, а не как плитка.
 */
const BODY_CAUSTICS: WaterLayer[] = [
  { material: 'caustics', periodIpari: 1.7, angle: 0.12, alpha: 0.16, speedIpari: 0.08, skew: 0, additive: true },
  {
    material: 'caustics',
    periodIpari: 2.93,
    angle: -0.58,
    alpha: 0.08,
    speedIpari: 0.045,
    skew: 0.85,
    additive: true,
  },
];

/** Скорость течения по умолчанию, ипари в секунду. */
const FLOW_SPEED_IPARI = 0.25;

/**
 * Слои плоскости воды: два масштаба ряби и блики.
 *
 * Периоды нарочно несоизмеримы: при кратных тайлах рисунок повторялся бы через
 * каждый тайл, а так сумма слоёв повторяется через десятки ипари.
 */
const PLANE_LAYERS: WaterLayer[] = [
  { material: 'ripples', periodIpari: 8.93, angle: 0, alpha: 0.4, speedIpari: FLOW_SPEED_IPARI * 0.8, skew: 0 },
  {
    material: 'ripples',
    periodIpari: 5.31,
    angle: Math.PI / 3.4,
    alpha: 0.24,
    speedIpari: FLOW_SPEED_IPARI * 1.3,
    skew: 0.45,
  },
  {
    material: 'glints',
    periodIpari: 4.13,
    angle: Math.PI / 7,
    alpha: 0.32,
    speedIpari: FLOW_SPEED_IPARI * 2.6,
    skew: -0.3,
    additive: true,
    fadeTile: 150,
  },
];

/**
 * Каустика на поверхности: один слой поверх плотной ряби.
 *
 * В толще каустика уже есть, но под рябью почти не читается. Слой добавляет
 * игру света и не трогает плотность воды: он аддитивный.
 */
const SURFACE_CAUSTICS: WaterLayer[] = [
  {
    material: 'caustics',
    periodIpari: 2.9,
    angle: 0.32,
    alpha: 0.1,
    speedIpari: 0.05,
    skew: 0.25,
    additive: true,
    fadeTile: 120,
  },
];

/** Насколько тише каустика на поверхности у замкнутого водоёма. */
const POND_SURFACE_CAUSTICS = 0.8;

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
const POND_CAUSTICS = 0.6;

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

  /** Плоскость: рябь, блики, макро-вариация и пена; под декором и актёрами. */
  paintPlane(layer: Container, element: SceneElement, resolution: number, zoom: number): SceneWaterNode | null {
    const geometry = this.geometry(element.shape, zoom);
    const fallback = this.materials.texture('ripples');
    if (geometry === null || fallback === null) {
      return null;
    }

    const base = Math.max(1, resolution);
    const pond = geometry.along === undefined;
    const nodes: SceneWaterNode[] = [];
    for (const spec of PLANE_LAYERS) {
      const texture = this.materials.texture(spec.material) ?? fallback;
      const period = base * spec.periodIpari;
      const alpha = spec.alpha * this.lod(spec, period, zoom) * (pond ? POND_ALPHA : 1);
      if (alpha < 0.01) {
        continue;
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
        continue;
      }

      if (spec.additive === true) {
        node.mesh.blendMode = 'add';
      }

      layer.addChild(node.mesh);
      nodes.push({ update: node.update });
    }

    const surface = this.materials.texture('caustics');
    if (surface !== null) {
      for (const spec of SURFACE_CAUSTICS) {
        const period = base * spec.periodIpari;
        const alpha = spec.alpha * this.lod(spec, period, zoom) * (pond ? POND_SURFACE_CAUSTICS : 1);
        if (alpha < 0.01) {
          continue;
        }

        const node = this.buildLayer(
          surface,
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
  paintFoam(layer: Container, element: SceneElement, resolution: number, zoom: number): SceneWaterNode | null {
    const foam = this.materials.texture('foam');
    const outline = this.outline(element.shape, zoom);
    if (foam === null || outline === null) {
      return null;
    }

    const base = Math.max(1, resolution);
    const band = base * FOAM_IPARI;
    const period = band * (foam.width / Math.max(1, foam.height)) * FOAM_STRETCH;
    const patches = this.patchesOf(element.id);
    const toward = this.waterward(element, resolution, outline);
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
  depthAt(element: SceneElement, resolution: number, point: ScenePoint): number {
    return this.depths.depthAt(element, resolution, point);
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
