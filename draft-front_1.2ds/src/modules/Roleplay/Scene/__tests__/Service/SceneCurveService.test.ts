import { describe, expect, it } from 'vitest';
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
      4,
    );

    expect(points[0]).toEqual([0, 0]);
    expect(points[points.length - 1]).toEqual([300, 0]);
    expect(points.every((point) => point[1] === 0)).toBe(true);
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
        { in: [0, 0], out: [80, 0] },
        { in: [-80, 0], out: [0, 0] },
        { in: [0, 0], out: [0, 0] },
        { in: [0, 0], out: [0, 0] },
      ],
      8,
    );

    expect(outline.length).toBeGreaterThan(4);
    expect(outline.some((point) => point[1] !== 0 && point[0] > 20 && point[0] < 80)).toBe(true);
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
