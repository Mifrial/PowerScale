import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import type { SceneGrassTrail } from '@/modules/Roleplay/Scene/Dto/SceneGrassTrail';
import { SCENE_GRASS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_GRASS';

/** Точка следа: место, направление движения и оставшаяся сила. */
interface TrailPoint {
  x: number;
  y: number;
  flowX: number;
  flowY: number;
  strength: number;
}

/**
 * Временный след движения по короткому ковру.
 *
 * Точки ставятся вдоль пути с шагом и гаснут за фиксированное время: стоящий
 * актор следа не оставляет, а прошедший — оставляет полосу, которая
 * распрямляется. Направление наклона — по ходу движения, не от центра.
 */
export class SceneGrassTrailService {
  private points: TrailPoint[] = [];

  private readonly last = new Map<number, { x: number; y: number }>();

  /** Записывает новые точки по смещению акторов. */
  record(actors: readonly SceneActor[], resolution: number): void {
    const base = Math.max(1, resolution);
    const step = base * SCENE_GRASS.trailStepIpari;
    const reset = base * SCENE_GRASS.trailResetIpari;
    for (const actor of actors) {
      if (actor.ghost === true) {
        continue;
      }

      const previous = this.last.get(actor.id);
      if (previous === undefined) {
        this.last.set(actor.id, { x: actor.x, y: actor.y });

        continue;
      }

      const deltaX = actor.x - previous.x;
      const deltaY = actor.y - previous.y;
      const distance = Math.hypot(deltaX, deltaY);
      if (distance < step) {
        continue;
      }

      this.last.set(actor.id, { x: actor.x, y: actor.y });
      if (distance > reset) {
        continue;
      }

      this.push({
        x: actor.x,
        y: actor.y,
        flowX: deltaX / distance,
        flowY: deltaY / distance,
        strength: 1,
      });
    }
  }

  /** Гасит след; true — ещё что-то осталось. */
  advance(deltaMs: number): boolean {
    const relax = Math.max(1, SCENE_GRASS.trailRelaxMs);
    for (const point of this.points) {
      point.strength -= deltaMs / relax;
    }

    this.points = this.points.filter((point) => point.strength > 0.02);

    return this.points.length > 0;
  }

  /** Есть ли живые точки следа. */
  hasPoints(): boolean {
    return this.points.length > 0;
  }

  /** Убирает след и память о прошлых позициях. */
  reset(): void {
    this.points = [];
    this.last.clear();
  }

  /** Упаковывает свежие точки в юниформы шейдера. */
  pack(resolution: number): SceneGrassTrail {
    const spots = new Float32Array(SCENE_GRASS.trailSpots * 4);
    const count = Math.min(this.points.length, SCENE_GRASS.trailSpots);
    const first = this.points.length - count;
    for (let index = 0; index < count; index += 1) {
      const point = this.points[first + index];
      const offset = index * 4;
      spots[offset] = point.x;
      spots[offset + 1] = point.y;
      spots[offset + 2] = point.flowX * point.strength;
      spots[offset + 3] = point.flowY * point.strength;
    }

    return {
      spots,
      count,
      span: Math.max(1, resolution) * SCENE_GRASS.carpetHeightIpari,
      width: Math.max(1, resolution) * SCENE_GRASS.trailWidthIpari,
    };
  }

  /** Добавляет точку, вытесняя самую старую. */
  private push(point: TrailPoint): void {
    this.points.push(point);
    if (this.points.length > SCENE_GRASS.trailSpots) {
      this.points.shift();
    }
  }
}
