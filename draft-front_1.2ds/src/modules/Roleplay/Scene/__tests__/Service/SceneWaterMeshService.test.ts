import { describe, expect, it } from 'vitest';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import { SceneWaterMeshService } from '@/modules/Roleplay/Scene/Service/SceneWaterMeshService';

const square = (size: number): ScenePoint[] => [
  [0, 0],
  [size, 0],
  [size, size],
  [0, size],
];

const circle = (radius: number, segments: number): ScenePoint[] =>
  Array.from({ length: segments }, (_, index) => {
    const angle = (index / segments) * Math.PI * 2;

    return [Math.cos(angle) * radius, Math.sin(angle) * radius] as ScenePoint;
  });

describe('SceneWaterMeshService', () => {
  const service = new SceneWaterMeshService();

  it('кладёт UV вдоль русла: u — длина по осевой, v — поперёк', () => {
    const uvs = service.channelUvs(new Float32Array([0, 10, 20]), new Float32Array([0, 0, 0]), 10, 0);

    expect(Array.from(uvs)).toEqual([0, 0, 1, 0, 2, 0]);
  });

  it('поворачивает выборку вдоль русла на заданный угол', () => {
    const uvs = service.channelUvs(new Float32Array([0, 10]), new Float32Array([5, 5]), 10, Math.PI / 2);

    expect(uvs[0]).toBeCloseTo(-0.5);
    expect(uvs[1]).toBeCloseTo(0);
    expect(uvs[2]).toBeCloseTo(-0.5);
    expect(uvs[3]).toBeCloseTo(1);
  });

  it('строит замкнутую полосу пены по контуру', () => {
    const strip = service.foamStrip(square(40), 2, 10, () => 1);

    expect(strip).not.toBeNull();
    const geometry = strip as NonNullable<typeof strip>;
    expect(geometry.positions.length).toBe(4 * 4);
    expect(geometry.indices.length).toBe(4 * 6);
    expect(geometry.period).toBeCloseTo(10);
  });

  it('держит кромки полосы на равном отступе от берега', () => {
    const strip = service.foamStrip(circle(100, 480), 3, 10, () => 1);
    const geometry = strip as NonNullable<typeof strip>;

    for (let index = 0; index < 480; index += 1) {
      const inner = Math.hypot(geometry.positions[index * 4], geometry.positions[index * 4 + 1]);
      const outer = Math.hypot(geometry.positions[index * 4 + 2], geometry.positions[index * 4 + 3]);
      expect(inner).toBeCloseTo(97, 3);
      expect(outer).toBeCloseTo(103, 3);
    }
  });

  it('сводит шов: контур укладывается в целое число тайлов', () => {
    const strip = service.foamStrip(square(40), 2, 7, () => 1);
    const geometry = strip as NonNullable<typeof strip>;
    const first = geometry.uvs[0] as number;
    const last = geometry.uvs[geometry.uvs.length - 4] as number;

    expect(Math.round(160 / geometry.period)).toBe(23);
    // Последняя вершина не доходит до шва на одну сторону квадрата.
    expect(last + 40 / geometry.period).toBeCloseTo(first + 160 / geometry.period, 5);
  });

  it('гуляет шириной вдоль контура, но сходится на замыкании', () => {
    const wobble = (t: number): number => 1 + 0.3 * Math.sin(t * Math.PI * 4);
    const strip = service.foamStrip(circle(100, 64), 3, 10, wobble);
    const geometry = strip as NonNullable<typeof strip>;
    const widthAt = (index: number): number =>
      Math.hypot(
        geometry.positions[index * 4 + 2] - geometry.positions[index * 4],
        geometry.positions[index * 4 + 3] - geometry.positions[index * 4 + 1],
      );

    expect(widthAt(8)).toBeGreaterThan(widthAt(0));
    expect(wobble(0)).toBeCloseTo(wobble(1));
  });

  it('не строит полосу по вырожденному контуру', () => {
    expect(
      service.foamStrip(
        [
          [0, 0],
          [1, 1],
        ],
        2,
        10,
        () => 1,
      ),
    ).toBeNull();
    expect(
      service.foamStrip(
        [
          [0, 0],
          [0, 0],
          [0, 0],
        ],
        2,
        10,
        () => 1,
      ),
    ).toBeNull();
  });
});
