import { Mesh, MeshGeometry, earcut } from 'pixi.js';
import type { Container, Texture } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
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

/** Слой поверхности: масштаб тайла, поворот выборки и прозрачность. */
interface SurfaceLayer {
  periodIpari: number;
  angle: number;
  alpha: number;
  speedIpari: number;
  /** Смещение дрейфа относительно направления течения. */
  skew: number;
}

/** Цвет воды в каустике: слой только подсвечивает, а не закрашивает. */
const CAUSTICS_ALPHA = 0.15;

/** Скорость каустики, ипари в секунду. */
const CAUSTICS_SPEED_IPARI = 0.06;

/** Скорость течения по умолчанию, ипари в секунду. */
const FLOW_SPEED_IPARI = 0.22;

/** Слои поверхности: крупная и мелкая рябь с разным направлением. */
const SURFACE_LAYERS: SurfaceLayer[] = [
  { periodIpari: 2.4, angle: 0, alpha: 0.5, speedIpari: FLOW_SPEED_IPARI, skew: 0 },
  { periodIpari: 1.15, angle: Math.PI / 5, alpha: 0.26, speedIpari: FLOW_SPEED_IPARI * 1.7, skew: 0.5 },
];

/** Разбиение окружности для воды-круга. */
const CIRCLE_SEGMENTS = 72;

/**
 * Вода в два прохода.
 *
 * Толща (дно, глубина, каустика) живёт под актёрами, поверхность (рябь, линия
 * уреза) — над ними: так персонаж в броде стоит на дне, а не на воде.
 *
 * UV мировые: лента и полигон задают только силуэт, поэтому текстура не
 * растягивается на поворотах и не даёт стыков между сегментами.
 */
export class SceneWaterService {
  constructor(private readonly materials: SceneTerrainTextureService) {}

  /** Толща: каустика по дну; null, если материала нет. */
  buildBody(layer: Container, element: SceneElement, resolution: number, zoom: number): SceneWaterNode | null {
    const caustics = this.materials.texture('caustics');
    if (caustics === null) {
      return null;
    }

    const [flowX, flowY] = this.flowDirection(element);
    const period = this.materials.period('caustics', resolution);
    const layerNode = this.buildLayer(
      caustics,
      this.geometry(element.shape, zoom),
      period,
      0,
      CAUSTICS_ALPHA,
      CAUSTICS_SPEED_IPARI,
      [flowX, flowY],
    );
    if (layerNode === null) {
      return null;
    }

    layerNode.mesh.blendMode = 'add';
    layer.addChild(layerNode.mesh);

    return { update: layerNode.update };
  }

  /** Поверхность: два слоя ряби; null, если материала нет. */
  buildSurface(layer: Container, element: SceneElement, resolution: number, zoom: number): SceneWaterNode | null {
    const surface = this.materials.texture('water');
    const geometry = this.geometry(element.shape, zoom);
    if (surface === null || geometry === null) {
      return null;
    }

    const [flowX, flowY] = this.flowDirection(element);
    const nodes: SceneWaterNode[] = [];
    for (const spec of SURFACE_LAYERS) {
      const node = this.buildLayer(
        surface,
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

    const angle = skew;
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);

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
}
