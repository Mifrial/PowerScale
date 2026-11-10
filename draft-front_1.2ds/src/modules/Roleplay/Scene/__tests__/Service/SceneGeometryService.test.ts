import { describe, expect, it } from 'vitest';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';

const service = new SceneGeometryService();

describe('SceneGeometryService', () => {
  it('computes bounds for every shape type', () => {
    expect(service.bounds({ type: 'rect', x: 10, y: 20, w: 100, h: 50 })).toEqual({
      x: 10,
      y: 20,
      width: 100,
      height: 50,
    });
    expect(service.bounds({ type: 'circle', cx: 50, cy: 60, r: 20 })).toEqual({
      x: 30,
      y: 40,
      width: 40,
      height: 40,
    });
    expect(service.bounds({ type: 'point', x: 5, y: 7 })).toEqual({ x: 5, y: 7, width: 0, height: 0 });
    expect(
      service.bounds({
        type: 'wall',
        points: [
          [0, 0],
          [100, 0],
        ],
        width: 10,
      }),
    ).toEqual({ x: -5, y: -5, width: 110, height: 10 });
  });

  it('hits a rect inside and ignores points outside the tolerance', () => {
    const shape: SceneShape = { type: 'rect', x: 0, y: 0, w: 100, h: 100 };

    expect(service.contains(shape, [50, 50], 0)).toBe(true);
    expect(service.contains(shape, [-5, 50], 0)).toBe(false);
    expect(service.contains(shape, [-5, 50], 8)).toBe(true);
  });

  it('hits a circle only within the radius plus tolerance', () => {
    const shape: SceneShape = { type: 'circle', cx: 50, cy: 50, r: 20 };

    expect(service.contains(shape, [50, 50], 0)).toBe(true);
    expect(service.contains(shape, [75, 50], 0)).toBe(false);
    expect(service.contains(shape, [75, 50], 6)).toBe(true);
  });

  it('hits a wall by its half width plus tolerance', () => {
    const shape: SceneShape = {
      type: 'wall',
      points: [
        [0, 0],
        [100, 0],
      ],
      width: 10,
    };

    expect(service.contains(shape, [50, 4], 0)).toBe(true);
    expect(service.contains(shape, [50, 20], 0)).toBe(false);
    expect(service.contains(shape, [50, 10], 6)).toBe(true);
  });

  it('hits a polygon by interior or contour tolerance', () => {
    const shape: SceneShape = {
      type: 'polygon',
      points: [
        [0, 0],
        [100, 0],
        [100, 100],
        [0, 100],
      ],
    };

    expect(service.contains(shape, [50, 50], 0)).toBe(true);
    expect(service.contains(shape, [120, 50], 0)).toBe(false);
    expect(service.contains(shape, [104, 50], 6)).toBe(true);
  });

  it('hits a point marker within tolerance', () => {
    const shape: SceneShape = { type: 'point', x: 100, y: 100 };

    expect(service.contains(shape, [103, 104], 5)).toBe(true);
    expect(service.contains(shape, [120, 120], 5)).toBe(false);
  });

  it('returns the center of a shape', () => {
    expect(service.center({ type: 'rect', x: 0, y: 0, w: 200, h: 100 })).toEqual([100, 50]);
  });
});
