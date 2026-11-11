import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Полоса вдоль контура: позиции, UV и треугольники. */
export interface WaterStrip {
  positions: Float32Array;
  uvs: Float32Array;
  indices: Uint32Array;
  /** Период текстуры вдоль контура; подобран так, чтобы шов замыкался. */
  period: number;
}

/** Предел растяжения митры на острых углах. */
const MITER_LIMIT = 2;

/**
 * Треугольники полосы вдоль контура.
 *
 * Полоса строится по нормалям контура: внутренняя и внешняя кромки отстоят от
 * линии берега на половину ширины. Ширина гуляет вдоль контура, поэтому пена не
 * выглядит лентой постоянной толщины.
 *
 * UV: `u` — длина вдоль контура, `v` — поперёк. Период подгоняется под целое
 * число тайлов, иначе на замыкании контура был бы видимый шов.
 */
export class SceneWaterMeshService {
  /** UV вдоль русла: `u` — длина по осевой, `v` — поперёк. */
  channelUvs(along: Float32Array, across: Float32Array, period: number, angle: number): Float32Array {
    const count = along.length;
    const uvs = new Float32Array(count * 2);
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    for (let index = 0; index < count; index += 1) {
      const u = along[index] / period;
      const v = across[index] / period;
      uvs[index * 2] = u * cos - v * sin;
      uvs[index * 2 + 1] = u * sin + v * cos;
    }

    return uvs;
  }

  /** UV по мировым координатам: для замкнутых водоёмов без русла. */
  worldUvs(positions: Float32Array, period: number, angle: number): Float32Array {
    const count = positions.length / 2;
    const uvs = new Float32Array(count * 2);
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    for (let index = 0; index < count; index += 1) {
      const x = positions[index * 2] / period;
      const y = positions[index * 2 + 1] / period;
      uvs[index * 2] = x * cos - y * sin;
      uvs[index * 2 + 1] = x * sin + y * cos;
    }

    return uvs;
  }

  /**
   * Полоса пены вдоль замкнутого контура.
   *
   * `wobble` получает долю пройденного пути (0…1) и возвращает множитель
   * ширины; он обязан быть периодическим, чтобы полоса сходилась на замыкании.
   */
  foamStrip(
    outline: ScenePoint[],
    halfWidth: number,
    period: number,
    wobble: (t: number) => number,
  ): WaterStrip | null {
    const count = outline.length;
    if (count < 3) {
      return null;
    }

    const lengths: number[] = [];
    const cumulatives: number[] = [];
    const normals: ScenePoint[] = [];
    let total = 0;
    for (let index = 0; index < count; index += 1) {
      const start = outline[index];
      const end = outline[(index + 1) % count];
      const dx = end[0] - start[0];
      const dy = end[1] - start[1];
      const length = Math.hypot(dx, dy);
      lengths.push(length);
      cumulatives.push(total);
      total += length;
      normals.push(length === 0 ? [0, 0] : [dy / length, -dx / length]);
    }

    if (total <= 0) {
      return null;
    }

    const tiles = Math.max(1, Math.round(total / Math.max(1, period)));
    const effective = total / tiles;
    const positions = new Float32Array(count * 4);
    const uvs = new Float32Array(count * 4);
    const indices = new Uint32Array(count * 6);
    for (let index = 0; index < count; index += 1) {
      const point = outline[index];
      const previous = normals[(index + count - 1) % count];
      const next = normals[index];
      const sumX = previous[0] + next[0];
      const sumY = previous[1] + next[1];
      const sum = Math.hypot(sumX, sumY);
      const axis: ScenePoint = sum === 0 ? next : [sumX / sum, sumY / sum];
      const projection = axis[0] * next[0] + axis[1] * next[1];
      const miter = Math.min(MITER_LIMIT, 1 / Math.max(0.35, Math.abs(projection)));
      const width = Math.max(1, halfWidth) * wobble(cumulatives[index] / total) * miter;

      const vertex = index * 2;
      positions[vertex * 2] = point[0] - axis[0] * width;
      positions[vertex * 2 + 1] = point[1] - axis[1] * width;
      positions[vertex * 2 + 2] = point[0] + axis[0] * width;
      positions[vertex * 2 + 3] = point[1] + axis[1] * width;

      const along = cumulatives[index] / effective;
      uvs[vertex * 2] = along;
      uvs[vertex * 2 + 1] = 0;
      uvs[vertex * 2 + 2] = along;
      uvs[vertex * 2 + 3] = 1;

      const neighbour = (index + 1) % count;
      const quad = index * 6;
      indices[quad] = vertex;
      indices[quad + 1] = vertex + 1;
      indices[quad + 2] = neighbour * 2;
      indices[quad + 3] = vertex + 1;
      indices[quad + 4] = neighbour * 2 + 1;
      indices[quad + 5] = neighbour * 2;
    }

    return { positions, uvs, indices, period: effective };
  }
}
