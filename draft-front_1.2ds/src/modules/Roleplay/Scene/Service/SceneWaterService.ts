import { FillPattern, Graphics, Matrix, Mesh, MeshGeometry, Sprite, earcut } from 'pixi.js';
import type { Container, Texture } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
import { SceneWaterDepthService } from '@/modules/Roleplay/Scene/Service/SceneWaterDepthService';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

/** Узел воды: обновление анимации по времени. */
export interface SceneWaterNode {
  update(seconds: number): void;
}

/** Геометрия поверхности: позиции и треугольники. */
interface WaterGeometry {
  positions: Float32Array;
  indices: Uint32Array;
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

/** Разбиение окружности для воды-круга. */
const CIRCLE_SEGMENTS = 72;

/** Ширина линии уреза в ипари. */
const FOAM_IPARI = 0.26;

/**
 * Вода: толща, плоскость и урез.
 *
 * - Толща (градиент глубины и каустика) — под актёрами.
 * - Плоскость (рябь и блики) — над толщей, но под декором и актёрами: это
 *   поверхность водоёма, и объект над ней её перекрывает.
 * - Урез (пена) — над всем: он и есть граница воды.
 *
 * Погружение объекта считается отдельно, по глубине под его нижней точкой.
 * UV мировые, поэтому текстура не растягивается на поворотах и не даёт стыков.
 */
export class SceneWaterService {
  private readonly depths = new SceneWaterDepthService();

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

    const [flowX, flowY] = this.flowDirection(element);
    const node = this.buildLayer(
      caustics,
      this.geometry(element.shape, zoom),
      Math.max(1, resolution) * 1.7,
      0,
      CAUSTICS_ALPHA,
      CAUSTICS_SPEED_IPARI,
      [flowX, flowY],
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

    const [flowX, flowY] = this.flowDirection(element);
    const nodes: SceneWaterNode[] = [];
    for (const spec of PLANE_LAYERS) {
      const texture = this.materials.texture(spec.material) ?? fallback;
      const node = this.buildLayer(
        texture,
        geometry,
        Math.max(1, resolution) * spec.periodIpari,
        spec.angle,
        spec.alpha,
        spec.speedIpari,
        this.skewed(flowX, flowY, spec.skew),
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

  /** Урез: мягкая полоса, рваная пена и тонкая линия поверх всего. */
  paintFoam(layer: Container, element: SceneElement, resolution: number, zoom: number): void {
    const graphics = new Graphics();
    this.trace(graphics, element.shape, zoom);
    graphics.stroke({
      color: 0xcfeaff,
      width: Math.max(1, resolution * 0.3),
      alpha: 0.12,
      alignment: 1,
      join: 'round',
    });

    const foam = this.materials.texture('foam');
    if (foam !== null) {
      this.trace(graphics, element.shape, zoom);
      graphics.stroke({
        fill: this.patternOf(foam, this.materials.period('foam', resolution), element.id + 53),
        width: Math.max(1.5, resolution * FOAM_IPARI),
        alpha: 0.6,
        alignment: 0.5,
        join: 'round',
      });
    }

    this.trace(graphics, element.shape, zoom);
    graphics.stroke({
      color: 0xe8f7ff,
      width: Math.max(1, 1.2 / zoom),
      alpha: 0.3,
      join: 'round',
    });
    layer.addChild(graphics);
  }

  /** Глубина под точкой в единицах сцены; вне воды — ноль. */
  depthAt(element: SceneElement, resolution: number, point: ScenePoint): number {
    return this.depths.depthAt(element, resolution, point);
  }

  /** Освобождает закэшированные поля глубины. */
  destroy(): void {
    this.depths.destroy();
  }

  /** Меш слоя: мировые UV, дрейф вдоль течения. */
  private buildLayer(
    texture: Texture,
    geometry: WaterGeometry | null,
    period: number,
    angle: number,
    alpha: number,
    speed: number,
    flow: ScenePoint,
  ): { mesh: Mesh; update(seconds: number): void } | null {
    if (geometry === null) {
      return null;
    }

    const count = geometry.positions.length / 2;
    const base = new Float32Array(count * 2);
    const uvs = new Float32Array(count * 2);
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    for (let index = 0; index < count; index += 1) {
      const x = geometry.positions[index * 2];
      const y = geometry.positions[index * 2 + 1];
      base[index * 2] = (x * cos - y * sin) / period;
      base[index * 2 + 1] = (x * sin + y * cos) / period;
    }

    const mesh = new Mesh({
      geometry: new MeshGeometry({ positions: geometry.positions, uvs, indices: geometry.indices }),
      texture,
    });
    mesh.alpha = alpha;

    const driftX = (cos * flow[0] - sin * flow[1]) / period;
    const driftY = (sin * flow[0] + cos * flow[1]) / period;

    return {
      mesh,
      update: (seconds: number): void => {
        const offsetX = driftX * speed * seconds;
        const offsetY = driftY * speed * seconds;
        for (let index = 0; index < count; index += 1) {
          uvs[index * 2] = base[index * 2] + offsetX;
          uvs[index * 2 + 1] = base[index * 2 + 1] + offsetY;
        }

        mesh.geometry.uvs = uvs;
      },
    };
  }

  /** Паттерн материала с тайлом в единицах сцены. */
  private patternOf(texture: Texture, period: number, elementId: number): FillPattern {
    const seed = Math.abs(Math.trunc(elementId)) * 2654435761;
    const offsetX = ((seed % 1024) / 1024) * period;
    const offsetY = (((seed >>> 10) % 1024) / 1024) * period;
    const matrix = new Matrix().scale(period / Math.max(1, texture.width), period / Math.max(1, texture.width));
    matrix.translate(offsetX, offsetY);
    const pattern = new FillPattern({ texture, textureSpace: 'global' });
    pattern.setTransform(matrix);

    return pattern;
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

  /** Поворот направления дрейфа: слои не идут строго параллельно. */
  private skewed(x: number, y: number, skew: number): ScenePoint {
    if (skew === 0) {
      return [x, y];
    }

    const cos = Math.cos(skew);
    const sin = Math.sin(skew);

    return [x * cos - y * sin, x * sin + y * cos];
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
    const indices = new Uint32Array((count - 1) * 6);
    for (let index = 0; index < count; index += 1) {
      const left = geometry.left[index];
      const right = geometry.right[index];
      const vertex = index * 2;
      positions[vertex * 2] = left[0];
      positions[vertex * 2 + 1] = left[1];
      positions[vertex * 2 + 2] = right[0];
      positions[vertex * 2 + 3] = right[1];
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

    return { positions, indices };
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
