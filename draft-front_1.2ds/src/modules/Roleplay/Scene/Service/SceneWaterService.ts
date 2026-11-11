import { Graphics, Mesh, MeshGeometry, Sprite, earcut } from 'pixi.js';
import type { Container, Texture } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
import { SceneWaterDepthService } from '@/modules/Roleplay/Scene/Service/SceneWaterDepthService';
import { SceneWaterMeshService } from '@/modules/Roleplay/Scene/Service/SceneWaterMeshService';
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
  material: 'ripples' | 'caustics' | 'glints';
  periodIpari: number;
  angle: number;
  alpha: number;
  speedIpari: number;
  /** Смещение дрейфа относительно направления течения. */
  skew: number;
  /** Аддитивное смешивание для бликов. */
  additive?: boolean;
}

/** Слой пены: ширина от базовой полосы, скорость вдоль берега и сдвиг фазы. */
interface FoamLayer {
  width: number;
  periodScale: number;
  alpha: number;
  speedIpari: number;
  offset: number;
}

/** Каустика по дну: подсвечивает, а не закрашивает. */
const CAUSTICS_ALPHA = 0.2;

/** Скорость каустики, ипари в секунду. */
const CAUSTICS_SPEED_IPARI = 0.08;

/** Скорость течения по умолчанию, ипари в секунду. */
const FLOW_SPEED_IPARI = 0.25;

/**
 * Слои плоскости воды: крупная рябь, каустика и блики.
 *
 * Разные масштабы и скорости дают переливы, а не «ползущую картинку»:
 * сумма слоёв меняется, даже когда каждый едет равномерно.
 */
const PLANE_LAYERS: WaterLayer[] = [
  { material: 'ripples', periodIpari: 3.2, angle: 0, alpha: 0.42, speedIpari: FLOW_SPEED_IPARI, skew: 0 },
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

/**
 * Слои пены: рваная полоса по урезу и широкая полупрозрачная дымка под ней.
 *
 * Полосы идут с разной скоростью в разные стороны: пятна пены наплывают друг
 * на друга и перестраиваются, поэтому урез не выглядит одной ползущей картинкой.
 */
const FOAM_LAYERS: FoamLayer[] = [
  { width: 1.35, periodScale: 1, alpha: 0.75, speedIpari: 0.12, offset: 0.13 },
  { width: 2.3, periodScale: 2.7, alpha: 0.22, speedIpari: -0.07, offset: 0.61 },
];

/** Ширина полосы пены в ипари. */
const FOAM_IPARI = 0.3;

/** Разбиение окружности для воды-круга. */
const CIRCLE_SEGMENTS = 72;

/**
 * Вода: толща, плоскость и урез.
 *
 * - Толща (градиент глубины и каустика) — под актёрами.
 * - Плоскость (рябь, блики, пена) — над толщей и под декором: это поверхность
 *   водоёма, и объект над ней её перекрывает.
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

    const period = Math.max(1, resolution) * 1.7;
    const node = this.buildLayer(
      caustics,
      this.geometry(element.shape, zoom),
      period,
      0,
      CAUSTICS_ALPHA,
      this.drift(element, period, 0, CAUSTICS_SPEED_IPARI * Math.max(1, resolution)),
    );
    if (node === null) {
      return null;
    }

    node.mesh.blendMode = 'add';
    layer.addChild(node.mesh);

    return { update: node.update };
  }

  /** Плоскость: рябь, каустика и блики; под декором и актёрами. */
  paintPlane(layer: Container, element: SceneElement, resolution: number, zoom: number): SceneWaterNode | null {
    const geometry = this.geometry(element.shape, zoom);
    const fallback = this.materials.texture('ripples');
    if (geometry === null || fallback === null) {
      return null;
    }

    const nodes: SceneWaterNode[] = [];
    for (const spec of PLANE_LAYERS) {
      const texture = this.materials.texture(spec.material) ?? fallback;
      const period = Math.max(1, resolution) * spec.periodIpari;
      const node = this.buildLayer(
        texture,
        geometry,
        period,
        spec.angle,
        spec.alpha,
        this.drift(element, period, spec.skew, spec.speedIpari * Math.max(1, resolution)),
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

  /** Урез: мокрый берег, линия воды и бегущая пена. */
  paintFoam(layer: Container, element: SceneElement, resolution: number, zoom: number): SceneWaterNode | null {
    const rim = new Graphics();
    this.trace(rim, element.shape, zoom);
    rim.stroke({
      color: 0xbfe3f2,
      width: Math.max(1, resolution * 0.34),
      alpha: 0.1,
      alignment: 1,
      join: 'round',
    });
    this.trace(rim, element.shape, zoom);
    rim.stroke({
      color: 0xe8f7ff,
      width: Math.max(1, 1.2 / zoom),
      alpha: 0.22,
      join: 'round',
    });
    layer.addChild(rim);

    const foam = this.materials.texture('foam');
    const outline = this.outline(element.shape, zoom);
    if (foam === null || outline === null) {
      return null;
    }

    // Пена мелкая: тайл 512×128 растянут вдоль берега, иначе рисунок
    // повторялся бы каждые полтора метра.
    const band = Math.max(1, resolution) * FOAM_IPARI;
    const period = band * (foam.width / Math.max(1, foam.height));
    const wobble = this.wobbleOf(element.id);
    const nodes: { mesh: Mesh; base: Float32Array; uvs: Float32Array; drift: number; offset: number }[] = [];
    for (const spec of FOAM_LAYERS) {
      const strip = this.meshes.foamStrip(outline, (band * spec.width) / 2, period * spec.periodScale, wobble);
      if (strip === null) {
        continue;
      }

      const uvs = Float32Array.from(strip.uvs);
      const mesh = new Mesh({
        geometry: new MeshGeometry({ positions: strip.positions, uvs, indices: strip.indices }),
        texture: foam,
      });
      mesh.alpha = spec.alpha;
      layer.addChild(mesh);
      nodes.push({
        mesh,
        base: Float32Array.from(strip.uvs),
        uvs,
        drift: (spec.speedIpari * Math.max(1, resolution)) / strip.period,
        offset: spec.offset,
      });
    }

    if (nodes.length === 0) {
      return null;
    }

    return {
      update: (seconds: number): void => {
        for (const node of nodes) {
          const shift = node.offset + node.drift * seconds;
          for (let index = 0; index < node.uvs.length; index += 2) {
            node.uvs[index] = node.base[index] + shift;
          }

          node.mesh.geometry.uvs = node.uvs;
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
   * Меш слоя: базовые UV и постоянный дрейф.
   *
   * У ленты UV идут вдоль осевой, поэтому текстура не сползает с изгиба и не
   * накапливает сдвиг: движение вдоль русла — это сдвиг одной координаты.
   * `drift` — уже в долях тайла за секунду.
   */
  private buildLayer(
    texture: Texture,
    geometry: WaterGeometry | null,
    period: number,
    angle: number,
    alpha: number,
    drift: ScenePoint,
  ): { mesh: Mesh; update(seconds: number): void } | null {
    if (geometry === null) {
      return null;
    }

    const base =
      geometry.along !== undefined && geometry.across !== undefined
        ? this.meshes.channelUvs(geometry.along, geometry.across, period, angle)
        : this.meshes.worldUvs(geometry.positions, period, angle);
    const uvs = Float32Array.from(base);
    const mesh = new Mesh({
      geometry: new MeshGeometry({ positions: geometry.positions, uvs, indices: geometry.indices }),
      texture,
    });
    mesh.alpha = alpha;

    return {
      mesh,
      update: (seconds: number): void => {
        const offsetX = drift[0] * seconds;
        const offsetY = drift[1] * seconds;
        for (let index = 0; index < uvs.length; index += 2) {
          uvs[index] = base[index] + offsetX;
          uvs[index + 1] = base[index + 1] + offsetY;
        }

        mesh.geometry.uvs = uvs;
      },
    };
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

  /** Ширина пены вдоль контура: рваный урез, а не лента постоянной толщины. */
  private wobbleOf(seed: number): (t: number) => number {
    const random = (value: number): number => {
      const chunk = Math.sin(value * 127.1) * 43758.5453;

      return chunk - Math.floor(chunk);
    };
    const phaseA = random(seed) * Math.PI * 2;
    const phaseB = random(seed + 17) * Math.PI * 2;

    return (t: number): number =>
      1 + 0.24 * Math.sin(t * Math.PI * 6 + phaseA) + 0.14 * Math.sin(t * Math.PI * 14 + phaseB);
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
        const contour = sceneCurveService.flattenContour(
          shape.points,
          shape.handles ?? [],
          sceneCurveService.toleranceFor(zoom),
        );
        graphics.poly(contour.flat());
        break;
      }
      case 'path': {
        const outline = sceneRibbonService.outline(
          shape.points,
          shape.handles ?? [],
          shape.widths,
          sceneCurveService.toleranceFor(zoom),
        );
        graphics.poly(outline.flat());
        break;
      }
      default:
        break;
    }
  }
}
