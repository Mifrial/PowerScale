import { describe, expect, it } from 'vitest';
import type { SceneGrassTuft } from '@/modules/Roleplay/Scene/Dto/SceneGrassTuft';
import { SCENE_GRASS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_GRASS';
import { SceneGrassMeshService } from '@/modules/Roleplay/Scene/Service/SceneGrassMeshService';

type Mesh = NonNullable<ReturnType<SceneGrassMeshService['build']>>;

const tuft = (overrides: Partial<SceneGrassTuft> = {}): SceneGrassTuft => ({
  x: 10,
  y: 20,
  variant: 0,
  height: 32,
  flip: false,
  rotation: 0,
  tint: 0xffffff,
  alpha: 1,
  sequence: 0,
  ...overrides,
});

const frames = [
  [0, 0, 0.5, 1],
  [0.5, 0, 1, 1],
] as const;

describe('SceneGrassMeshService', () => {
  const service = new SceneGrassMeshService();
  const half = (32 * SCENE_GRASS.tuftAspect) / 2;

  it('строит квад на пучок с UV в атлас', () => {
    const mesh = service.build([tuft()], frames) as Mesh;

    expect(mesh.positions.length).toBe(8);
    expect(mesh.uvs.length).toBe(8);
    expect(mesh.tints.length).toBe(16);
    expect(mesh.origins.length).toBe(8);
    expect(mesh.indices.length).toBe(6);
    // Основание на точке, вершина — на высоте пучка.
    expect(mesh.positions[0]).toBeCloseTo(10 - half);
    expect(mesh.positions[1]).toBeCloseTo(20);
    expect(mesh.positions[5]).toBeCloseTo(20 - 32);
    // UV: низ кадра — v1, верх — v0.
    expect(Array.from(mesh.uvs)).toEqual([0, 1, 0.5, 1, 0.5, 0, 0, 0]);
  });

  it('кладёт тинт и основание на все вершины', () => {
    const mesh = service.build([tuft({ tint: 0x804020, alpha: 0.5 })], frames) as Mesh;

    for (let vertex = 0; vertex < 4; vertex += 1) {
      expect(mesh.tints[vertex * 4]).toBeCloseTo(0x80 / 255);
      expect(mesh.tints[vertex * 4 + 1]).toBeCloseTo(0x40 / 255);
      expect(mesh.tints[vertex * 4 + 2]).toBeCloseTo(0x20 / 255);
      expect(mesh.tints[vertex * 4 + 3]).toBeCloseTo(0.5);
      expect(mesh.origins[vertex * 2]).toBeCloseTo(10);
      expect(mesh.origins[vertex * 2 + 1]).toBeCloseTo(20);
    }
  });

  it('поворачивает квад вокруг основания', () => {
    const mesh = service.build([tuft({ rotation: Math.PI / 2 })], frames) as Mesh;
    // Верхний правый угол после поворота на 90° уходит вправо и вниз.
    expect(mesh.positions[4]).toBeCloseTo(10 + 32);
    expect(mesh.positions[5]).toBeCloseTo(20 + half);
  });

  it('отражает квад по горизонтали', () => {
    const mesh = service.build([tuft({ flip: true })], frames) as Mesh;

    expect(mesh.positions[0]).toBeCloseTo(10 + half);
    expect(mesh.positions[2]).toBeCloseTo(10 - half);
  });

  it('сдвигает индексы для нескольких пучков', () => {
    const mesh = service.build([tuft(), tuft({ variant: 1 })], frames) as Mesh;

    expect(Array.from(mesh.indices.slice(0, 6))).toEqual([0, 1, 2, 0, 2, 3]);
    expect(Array.from(mesh.indices.slice(6))).toEqual([4, 5, 6, 4, 6, 7]);
    // Второй вариант берёт вторую рамку атласа.
    expect(mesh.uvs[8]).toBeCloseTo(0.5);
  });

  it('не строит меш без пучков или рамок', () => {
    expect(service.build([], frames)).toBeNull();
    expect(service.build([tuft()], [])).toBeNull();
  });
});
