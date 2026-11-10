import { describe, expect, it } from 'vitest';
import type { SceneHandle } from '@/modules/Roleplay/Scene/Dto/SceneHandle';
import { SceneCurveService } from '@/modules/Roleplay/Scene/Service/SceneCurveService';

const service = new SceneCurveService();

describe('SceneCurveService', () => {
  it('flattens a straight open path to its endpoints', () => {
    const points = service.flatten(
      [
        [0, 0],
        [300, 0],
      ],
      service.normalizeHandles([]),
    );

    expect(points).toEqual([
      [0, 0],
      [300, 0],
    ]);
  });

  it('bends a contour by bezier handles', () => {
    const outline = service.flattenContour(
      [
        [0, 0],
        [100, 0],
        [100, 100],
        [0, 100],
      ],
      [
        { in: [0, 0], out: [0, -60] },
        { in: [0, -60], out: [0, 0] },
        { in: [0, 0], out: [0, 0] },
        { in: [0, 0], out: [0, 0] },
      ],
      0.5,
    );

    expect(outline.length).toBeGreaterThan(4);
    expect(outline.some((point) => point[1] < -20 && point[0] > 20 && point[0] < 80)).toBe(true);
  });

  it('subdivides tighter tolerance into more samples', () => {
    const points: [number, number][] = [
      [0, 0],
      [2000, 0],
    ];
    const handles: SceneHandle[] = [
      { in: [0, 0], out: [900, 1200] },
      { in: [-900, 1200], out: [0, 0] },
    ];

    const coarse = service.flattenSamples(points, handles, 4);
    const fine = service.flattenSamples(points, handles, 0.25);

    expect(fine.length).toBeGreaterThan(coarse.length);
    expect(coarse[0].tangent[0]).toBeGreaterThan(0);
    expect(coarse[0].tangent[1]).toBeGreaterThan(0);
  });

  it('keeps the flattened path within the tolerance of the true curve', () => {
    const points: [number, number][] = [
      [0, 0],
      [2000, 0],
    ];
    const handles: SceneHandle[] = [
      { in: [0, 0], out: [900, 1400] },
      { in: [-900, 1400], out: [0, 0] },
    ];
    const start: [number, number] = [0, 0];
    const controlOne: [number, number] = [900, 1400];
    const controlTwo: [number, number] = [1100, 1400];
    const end: [number, number] = [2000, 0];
    const tolerance = 1;
    const flat = service.flatten(points, handles, tolerance);
    const distanceToFlat = (point: [number, number]): number => {
      let nearest = Number.POSITIVE_INFINITY;
      for (let index = 1; index < flat.length; index += 1) {
        const a = flat[index - 1];
        const b = flat[index];
        const dx = b[0] - a[0];
        const dy = b[1] - a[1];
        const lengthSquared = dx * dx + dy * dy;
        const t =
          lengthSquared === 0
            ? 0
            : Math.max(0, Math.min(1, ((point[0] - a[0]) * dx + (point[1] - a[1]) * dy) / lengthSquared));
        nearest = Math.min(nearest, Math.hypot(point[0] - (a[0] + t * dx), point[1] - (a[1] + t * dy)));
      }

      return nearest;
    };

    let worst = 0;
    for (let step = 0; step <= 200; step += 1) {
      const sample = service.cubicPoint(start, controlOne, controlTwo, end, step / 200);
      worst = Math.max(worst, distanceToFlat([sample[0], sample[1]]));
    }

    expect(worst).toBeLessThanOrEqual(tolerance);
  });

  it('keeps the analytic tangent on axis-aligned segments', () => {
    const samples = service.flattenSamples(
      [
        [0, 0],
        [300, 0],
        [300, 300],
      ],
      service.normalizeHandles([]),
    );

    expect(samples[0].tangent).toEqual([1, 0]);
    expect(samples[samples.length - 1].tangent[0]).toBeCloseTo(0, 6);
    expect(samples[samples.length - 1].tangent[1]).toBeCloseTo(1, 6);
  });

  it('splits a cubic segment without moving its shape', () => {
    const split = service.splitCubic([0, 0], [30, 0], [70, 100], [100, 100], 0.5);

    expect(split.left[0]).toEqual([0, 0]);
    expect(split.right[3]).toEqual([100, 100]);
    expect(split.left[3]).toEqual(split.right[0]);
  });

  it('builds smooth handles for interior vertices only', () => {
    const handles = service.autoHandles([
      [0, 0],
      [100, 50],
      [200, 0],
    ]);

    expect(handles[0]).toEqual({ in: [0, 0], out: [0, 0] });
    expect(handles[2]).toEqual({ in: [0, 0], out: [0, 0] });
    expect(handles[1].out[0]).toBeGreaterThan(0);
  });

  it('finds the nearest point on a cubic', () => {
    const nearest = service.nearestOnCubic([50, 20], [0, 0], [20, 0], [80, 100], [100, 100]);

    expect(nearest.distance).toBeLessThan(30);
    expect(nearest.t).toBeGreaterThan(0);
    expect(nearest.t).toBeLessThan(1);
  });
});
