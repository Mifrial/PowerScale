import { describe, expect, it } from 'vitest';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import { SCENE_GRASS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_GRASS';
import { SceneGrassTrailService } from '@/modules/Roleplay/Scene/Service/SceneGrassTrailService';

const RESOLUTION = 64;

function actor(id: number, x: number, y: number, extra: Partial<SceneActor> = {}): SceneActor {
  return { id, x, y, facing: 0, radius: 10, tint: 0xffffff, label: `actor-${id}`, ...extra };
}

describe('SceneGrassTrailService', () => {
  it('стоящий актор следа не оставляет', () => {
    const trail = new SceneGrassTrailService();
    trail.record([actor(1, 100, 100)], RESOLUTION);

    expect(trail.hasPoints()).toBe(false);
    expect(trail.pack(RESOLUTION).count).toBe(0);
  });

  it('пишет точку по ходу движения', () => {
    const trail = new SceneGrassTrailService();
    trail.record([actor(1, 100, 100)], RESOLUTION);
    trail.record([actor(1, 100 + RESOLUTION, 100)], RESOLUTION);

    const packed = trail.pack(RESOLUTION);
    expect(packed.count).toBe(1);
    expect(packed.spots[0]).toBe(100 + RESOLUTION);
    expect(packed.spots[1]).toBe(100);
    expect(packed.spots[2]).toBeCloseTo(1);
    expect(packed.spots[3]).toBeCloseTo(0);
    expect(packed.span).toBeCloseTo(RESOLUTION * SCENE_GRASS.carpetHeightIpari);
    expect(packed.width).toBeCloseTo(RESOLUTION * SCENE_GRASS.trailWidthIpari);
  });

  it('не ставит точки на мелком шаге и на телепорте', () => {
    const trail = new SceneGrassTrailService();
    trail.record([actor(1, 0, 0)], RESOLUTION);
    trail.record([actor(1, RESOLUTION * SCENE_GRASS.trailStepIpari * 0.5, 0)], RESOLUTION);
    expect(trail.hasPoints()).toBe(false);

    trail.record([actor(1, RESOLUTION * SCENE_GRASS.trailResetIpari * 2, 0)], RESOLUTION);
    expect(trail.hasPoints()).toBe(false);
  });

  it('не оставляет следов от призраков', () => {
    const trail = new SceneGrassTrailService();
    trail.record([actor(1, 0, 0, { ghost: true })], RESOLUTION);
    trail.record([actor(1, RESOLUTION, 0, { ghost: true })], RESOLUTION);

    expect(trail.hasPoints()).toBe(false);
  });

  it('гасит след за trailRelaxMs', () => {
    const trail = new SceneGrassTrailService();
    trail.record([actor(1, 0, 0)], RESOLUTION);
    trail.record([actor(1, RESOLUTION, 0)], RESOLUTION);

    expect(trail.advance(SCENE_GRASS.trailRelaxMs / 2)).toBe(true);
    expect(trail.pack(RESOLUTION).spots[2]).toBeCloseTo(0.5, 1);

    expect(trail.advance(SCENE_GRASS.trailRelaxMs / 2)).toBe(false);
    expect(trail.hasPoints()).toBe(false);
  });

  it('держит не больше trailSpots точек', () => {
    const trail = new SceneGrassTrailService();
    let x = 0;
    trail.record([actor(1, x, 0)], RESOLUTION);
    for (let index = 0; index < SCENE_GRASS.trailSpots + 5; index += 1) {
      x += RESOLUTION * SCENE_GRASS.trailStepIpari * 2;
      trail.record([actor(1, x, 0)], RESOLUTION);
    }

    expect(trail.pack(RESOLUTION).count).toBe(SCENE_GRASS.trailSpots);
  });
});
