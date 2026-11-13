import { Mesh, MeshGeometry } from 'pixi.js';
import type { Container, Shader } from 'pixi.js';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneTerrainBand } from '@/modules/Roleplay/Scene/Dto/SceneTerrainBand';
import type { SceneTerrainBlendOptions } from '@/modules/Roleplay/Scene/Dto/SceneTerrainBlendOptions';
import { SceneTerrainBlendShader } from '@/modules/Roleplay/Scene/Service/SceneTerrainBlendShader';

/** Предел растяжения митры на острых углах: без него шип уходит далеко. */
const MITER_LIMIT = 2;

/**
 * Рваная кромка террейна.
 *
 * Вдоль контура фигуры строится полоса наружу, и её материал неровно наползает
 * на соседа: у песка на траве край получается осыпью, а не линией заливки.
 * Полоса семплит ту же текстуру и тот же сдвиг, что заливка поверхности,
 * поэтому шва на краю нет.
 */
export class SceneTerrainBlendService {
  private readonly shader = new SceneTerrainBlendShader();

  /**
   * Полоса вдоль замкнутого контура.
   *
   * Внутренняя кромка лежит на самом контуре, внешняя вынесена наружу на
   * `width`. Направление наружу определяется знаком площади: фигуры рисуются в
   * любую сторону, а кромка всегда должна наползать на соседа, а не внутрь.
   */
  band(outline: readonly ScenePoint[], width: number): SceneTerrainBand | null {
    const points = this.dedup(outline);
    const count = points.length;
    if (count < 3 || width <= 0) {
      return null;
    }

    const area = this.signedArea(points);
    if (Math.abs(area) < 1e-6) {
      return null;
    }

    const toward = area > 0 ? 1 : -1;
    const normals: ScenePoint[] = [];
    for (let index = 0; index < count; index += 1) {
      const start = points[index];
      const end = points[(index + 1) % count];
      const dx = end[0] - start[0];
      const dy = end[1] - start[1];
      const length = Math.hypot(dx, dy);
      normals.push(length === 0 ? [0, 0] : [(dy / length) * toward, (-dx / length) * toward]);
    }

    const positions = new Float32Array(count * 4);
    const uvs = new Float32Array(count * 4);
    const indices = new Uint32Array(count * 6);
    for (let index = 0; index < count; index += 1) {
      const point = points[index];
      const previous = normals[(index + count - 1) % count];
      const next = normals[index];
      const sumX = previous[0] + next[0];
      const sumY = previous[1] + next[1];
      const sum = Math.hypot(sumX, sumY);
      const outward: ScenePoint = sum === 0 ? next : [sumX / sum, sumY / sum];
      const projection = outward[0] * next[0] + outward[1] * next[1];
      const miter = Math.min(MITER_LIMIT, 1 / Math.max(0.35, Math.abs(projection)));
      const vertex = index * 2;
      positions[vertex * 2] = point[0];
      positions[vertex * 2 + 1] = point[1];
      positions[vertex * 2 + 2] = point[0] + outward[0] * width * miter;
      positions[vertex * 2 + 3] = point[1] + outward[1] * width * miter;
      uvs[vertex * 2] = 0;
      uvs[vertex * 2 + 1] = index / count;
      uvs[vertex * 2 + 2] = 1;
      uvs[vertex * 2 + 3] = index / count;

      const neighbour = (index + 1) % count;
      const quad = index * 6;
      indices[quad] = vertex;
      indices[quad + 1] = vertex + 1;
      indices[quad + 2] = neighbour * 2;
      indices[quad + 3] = vertex + 1;
      indices[quad + 4] = neighbour * 2 + 1;
      indices[quad + 5] = neighbour * 2;
    }

    return { positions, uvs, indices };
  }

  /** Добавляет полосу кромки в слой; `null` — контур вырожден. */
  spill(
    layer: Container,
    outline: readonly ScenePoint[],
    options: SceneTerrainBlendOptions,
  ): Mesh<MeshGeometry, Shader> | null {
    const band = this.band(outline, options.solid + options.ragged);
    if (band === null) {
      return null;
    }

    const mesh = new Mesh({
      geometry: new MeshGeometry(band),
      shader: this.shader.create(options),
    });
    layer.addChild(mesh);

    return mesh;
  }

  /** Убирает повторы подряд: на них нормаль не определена. */
  private dedup(outline: readonly ScenePoint[]): ScenePoint[] {
    const points: ScenePoint[] = [];
    for (const point of outline) {
      const previous = points[points.length - 1];
      if (previous === undefined || Math.hypot(point[0] - previous[0], point[1] - previous[1]) > 1e-6) {
        points.push(point);
      }
    }

    const first = points[0];
    const last = points[points.length - 1];
    const closes = first !== undefined && last !== undefined && points.length > 1;
    if (closes && Math.hypot(first[0] - last[0], first[1] - last[1]) <= 1e-6) {
      points.pop();
    }

    return points;
  }

  /** Площадь контура со знаком: знак задаёт направление наружу. */
  private signedArea(points: readonly ScenePoint[]): number {
    let area = 0;
    for (let index = 0; index < points.length; index += 1) {
      const current = points[index];
      const next = points[(index + 1) % points.length];
      area += current[0] * next[1] - next[0] * current[1];
    }

    return area / 2;
  }
}
