import { Graphics, Mesh, MeshGeometry, TilingSprite } from 'pixi.js';
import type { Texture } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

/** Узел воды: геометрия или тайл плюс обновление анимации. */
export interface SceneWaterNode {
  node: Mesh | TilingSprite;
  mask?: Graphics;
  update(seconds: number): void;
}

/** Цикл течения: один тайл проходит за это число секунд. */
const FLOW_PERIOD_SECONDS = 6.5;

/** Дрейф замкнутой воды, пикселей текстуры в секунду. */
const DRIFT_PIXELS_PER_SECOND = 9;

/**
 * Вода: лента через `Mesh` с UV вдоль осевой, замкнутая фигура — `TilingSprite`.
 *
 * Анимация меняет только UV или `tilePosition`; геометрия не пересобирается.
 */
export class SceneWaterService {
  /** Создаёт воду по фигуре или null, если фигура пустая. */
  build(element: SceneElement, texture: Texture, resolution: number, zoom: number): SceneWaterNode | null {
    if (element.shape.type === 'path') {
      return this.buildFlow(element.shape, texture, resolution, zoom);
    }

    return this.buildDrift(element.shape, texture, resolution, zoom);
  }

  /** Лента: вершины по краям, UV вдоль осевой. */
  private buildFlow(
    shape: Extract<SceneShape, { type: 'path' }>,
    texture: Texture,
    resolution: number,
    zoom: number,
  ): SceneWaterNode | null {
    const tolerance = sceneCurveService.toleranceFor(zoom);
    const geometry = sceneRibbonService.geometry(shape.points, shape.handles ?? [], shape.widths, tolerance);
    const count = geometry.center.length;
    if (count < 2) {
      return null;
    }

    const period = Math.max(1, resolution) * 2;
    const positions = new Float32Array(count * 4);
    const uvs = new Float32Array(count * 4);
    const indices = new Uint32Array((count - 1) * 6);
    for (let index = 0; index < count; index += 1) {
      const left = geometry.left[index];
      const right = geometry.right[index];
      const vertex = index * 2;
      positions[vertex * 2] = left[0];
      positions[vertex * 2 + 1] = left[1];
      positions[vertex * 2 + 2] = right[0];
      positions[vertex * 2 + 3] = right[1];
      const v = geometry.lengths[index] / period;
      uvs[vertex * 2] = 0;
      uvs[vertex * 2 + 1] = v;
      uvs[vertex * 2 + 2] = Math.max(1, geometry.widths[index]) / period;
      uvs[vertex * 2 + 3] = v;
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

    const mesh = new Mesh({
      geometry: new MeshGeometry({ positions, uvs, indices }),
      texture,
    });
    const baseUvs = new Float32Array(uvs);
    mesh.alpha = 0.92;

    return {
      node: mesh,
      update: (seconds: number): void => {
        const phase = (seconds / FLOW_PERIOD_SECONDS) % 1;
        for (let index = 0; index < count; index += 1) {
          const v = baseUvs[index * 4 + 1] + phase;
          uvs[index * 4 + 1] = v % 1;
          uvs[index * 4 + 3] = v % 1;
        }

        mesh.geometry.uvs = uvs;
      },
    };
  }

  /** Замкнутая вода: тайл по границам фигуры плюс маска. */
  private buildDrift(shape: SceneShape, texture: Texture, resolution: number, zoom: number): SceneWaterNode | null {
    const bounds = sceneGeometryService.bounds(shape);
    if (bounds.width <= 0 || bounds.height <= 0) {
      return null;
    }

    const period = Math.max(1, resolution) * 2;
    const scale = period / 256;
    const sprite = new TilingSprite({
      texture,
      width: bounds.width,
      height: bounds.height,
      tileScale: { x: scale, y: scale },
    });
    sprite.position.set(bounds.x, bounds.y);
    sprite.alpha = 0.92;
    const baseX = (Math.abs(Math.trunc(bounds.x)) % 97) * 3;
    const baseY = (Math.abs(Math.trunc(bounds.y)) % 89) * 3;
    sprite.tilePosition.set(baseX, baseY);

    const mask = new Graphics();
    this.trace(mask, shape, zoom);
    mask.fill({ color: 0xffffff });
    sprite.mask = mask;

    return {
      node: sprite,
      mask,
      update: (seconds: number): void => {
        sprite.tilePosition.set(
          baseX + seconds * DRIFT_PIXELS_PER_SECOND,
          baseY + seconds * DRIFT_PIXELS_PER_SECOND * 0.45,
        );
      },
    };
  }

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
        graphics.poly(outline.flat());
        break;
      }
      case 'path': {
        const tolerance = sceneCurveService.toleranceFor(zoom);
        const outline = sceneRibbonService.outline(shape.points, shape.handles ?? [], shape.widths, tolerance);
        graphics.poly(outline.flat());
        break;
      }
      case 'point':
        graphics.circle(shape.x, shape.y, 9 / zoom);
        break;
      case 'wall':
        graphics.poly(shape.points.flat());
        break;
    }
  }
}
