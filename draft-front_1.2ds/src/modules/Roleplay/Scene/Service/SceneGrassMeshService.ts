import type { SceneGrassMesh } from '@/modules/Roleplay/Scene/Dto/SceneGrassMesh';
import type { SceneGrassTuft } from '@/modules/Roleplay/Scene/Dto/SceneGrassTuft';
import { SCENE_GRASS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_GRASS';

/** Прямоугольник кадра в атласе: u0, v0, u1, v1. */
type GrassFrame = readonly [number, number, number, number];

/**
 * Геометрия ковра травы.
 *
 * Каждый пучок — квад в мировых координатах: поворот, отражение и масштаб
 * запечены в вершины, поэтому шейдеру остаются только UV, тинт и точка
 * основания. Так тысячи пучков уходят в один меш и один draw call.
 */
export class SceneGrassMeshService {
  /** Собирает квады ковра; `null` — пустой набор. */
  build(tufts: readonly SceneGrassTuft[], frames: readonly GrassFrame[]): SceneGrassMesh | null {
    if (tufts.length === 0 || frames.length === 0) {
      return null;
    }

    const positions = new Float32Array(tufts.length * 8);
    const uvs = new Float32Array(tufts.length * 8);
    const tints = new Float32Array(tufts.length * 16);
    const origins = new Float32Array(tufts.length * 8);
    const indices = new Uint32Array(tufts.length * 6);
    for (let quad = 0; quad < tufts.length; quad += 1) {
      const tuft = tufts[quad];
      const frame = frames[tuft.variant % frames.length];
      const [u0, v0, u1, v1] = frame;
      const half = (tuft.height * SCENE_GRASS.tuftAspect) / 2;
      const cos = Math.cos(tuft.rotation);
      const sin = Math.sin(tuft.rotation);
      const flip = tuft.flip ? -1 : 1;
      const corners: [number, number][] = [
        [-half * flip, 0],
        [half * flip, 0],
        [half * flip, -tuft.height],
        [-half * flip, -tuft.height],
      ];
      const uv: [number, number][] = [
        [u0, v1],
        [u1, v1],
        [u1, v0],
        [u0, v0],
      ];
      const red = ((tuft.tint >> 16) & 255) / 255;
      const green = ((tuft.tint >> 8) & 255) / 255;
      const blue = (tuft.tint & 255) / 255;
      for (let corner = 0; corner < 4; corner += 1) {
        const [localX, localY] = corners[corner];
        const vertex = quad * 4 + corner;
        positions[vertex * 2] = tuft.x + localX * cos - localY * sin;
        positions[vertex * 2 + 1] = tuft.y + localX * sin + localY * cos;
        uvs[vertex * 2] = uv[corner][0];
        uvs[vertex * 2 + 1] = uv[corner][1];
        tints[vertex * 4] = red;
        tints[vertex * 4 + 1] = green;
        tints[vertex * 4 + 2] = blue;
        tints[vertex * 4 + 3] = tuft.alpha;
        origins[vertex * 2] = tuft.x;
        origins[vertex * 2 + 1] = tuft.y;
      }

      const vertex = quad * 4;
      const index = quad * 6;
      indices[index] = vertex;
      indices[index + 1] = vertex + 1;
      indices[index + 2] = vertex + 2;
      indices[index + 3] = vertex;
      indices[index + 4] = vertex + 2;
      indices[index + 5] = vertex + 3;
    }

    return { positions, uvs, tints, origins, indices };
  }
}
