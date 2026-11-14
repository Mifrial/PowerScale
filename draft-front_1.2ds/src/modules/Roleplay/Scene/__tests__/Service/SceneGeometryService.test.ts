import { describe, expect, it } from 'vitest';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
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

  it('поворачивает прямоугольник вокруг центра', () => {
    const shape: SceneShape = { type: 'rect', x: 0, y: 0, w: 100, h: 20 };

    expect(service.bounds(shape, 90)).toEqual({ x: 40, y: -40, width: 20, height: 100 });
    expect(service.contains(shape, [50, 50], 0, 90)).toBe(true);
    expect(service.contains(shape, [95, 10], 0, 90)).toBe(false);
    expect(service.contains(shape, [50, 50], 0)).toBe(false);
    expect(service.outline(shape, 0, 48, 90)).toHaveLength(4);
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

  it('замыкает кольцо при проверке расстояния', () => {
    const ring: ScenePoint[] = [
      [0, 0],
      [100, 0],
      [100, 100],
      [0, 100],
    ];

    // Точка слева от кольца ближе всего к замыкающему ребру (0,100)-(0,0).
    expect(service.distanceToPolyline([-4, 50], ring, true)).toBeCloseTo(4);
    expect(service.distanceToPolyline([-4, 50], ring)).toBeGreaterThan(10);
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

  it('accounts for bezier handles in polygon bounds', () => {
    const shape: SceneShape = {
      type: 'polygon',
      points: [
        [0, 0],
        [100, 0],
        [100, 100],
      ],
      handles: [
        { in: [0, 0], out: [40, -60] },
        { in: [-40, -60], out: [0, 0] },
        { in: [0, 0], out: [0, 0] },
      ],
    };

    const bounds = service.bounds(shape);

    expect(bounds.y).toBeLessThan(0);
    expect(bounds.height).toBeGreaterThan(100);
  });

  it('hits a ribbon by its width', () => {
    const shape: SceneShape = {
      type: 'path',
      points: [
        [0, 100],
        [300, 100],
      ],
      widths: [40, 40],
    };

    expect(service.contains(shape, [150, 100], 0)).toBe(true);
    expect(service.contains(shape, [150, 118], 0)).toBe(true);
    expect(service.contains(shape, [150, 130], 0)).toBe(false);
    expect(service.contains(shape, [150, 130], 15)).toBe(true);
  });

  it('accounts for ribbon width in bounds', () => {
    const bounds = service.bounds({
      type: 'path',
      points: [
        [0, 100],
        [300, 100],
      ],
      widths: [40, 40],
    });

    expect(bounds.x).toBeCloseTo(0, 6);
    expect(bounds.y).toBeCloseTo(80, 6);
    expect(bounds.width).toBeCloseTo(300, 6);
    expect(bounds.height).toBeCloseTo(40, 6);
  });

  it('даёт готовой проверке те же попадания, что и прямой contains', () => {
    const polygon: SceneShape = {
      type: 'polygon',
      points: [
        [0, 0],
        [100, 0],
        [100, 100],
        [0, 100],
      ],
    };
    const ribbon: SceneShape = {
      type: 'path',
      points: [
        [0, 100],
        [300, 100],
      ],
      widths: [40, 40],
    };
    const polygonProbe = service.probe(polygon);
    const ribbonProbe = service.probe(ribbon);

    const polygonPoints: ScenePoint[] = [
      [50, 50],
      [104, 50],
      [120, 50],
      [-3, 50],
    ];
    const ribbonPoints: ScenePoint[] = [
      [150, 100],
      [150, 118],
      [150, 130],
      [0, 150],
    ];
    for (const point of polygonPoints) {
      expect(polygonProbe.contains(point, 6)).toBe(service.contains(polygon, point, 6));
    }
    for (const point of ribbonPoints) {
      expect(ribbonProbe.contains(point, 15)).toBe(service.contains(ribbon, point, 15));
    }

    expect(polygonProbe.bounds).toEqual(service.bounds(polygon));
    expect(ribbonProbe.bounds).toEqual(service.bounds(ribbon));
  });

  it('hits a point marker within tolerance', () => {
    const shape: SceneShape = { type: 'point', x: 100, y: 100 };

    expect(service.contains(shape, [103, 104], 5)).toBe(true);
    expect(service.contains(shape, [120, 120], 5)).toBe(false);
  });

  it('returns the center of a shape', () => {
    expect(service.center({ type: 'rect', x: 0, y: 0, w: 200, h: 100 })).toEqual([100, 50]);
  });

  it('opens only the ends of a river that touch the board edge', () => {
    const board = { x: 0, y: 0, width: 2000, height: 2000 };
    const river: SceneShape = {
      type: 'path',
      points: [
        [0, 1000],
        [500, 1000],
      ],
      widths: [40, 40],
    };

    // Начало на левом краю поля — открыто, конец в глубине — закрыт (река в озеро).
    const pads = service.openEndPads(river, board, 64);
    expect(pads[0]).toBeGreaterThan(0);
    expect(pads[1]).toBe(0);

    expect(service.openEndPads(river, null, 64)).toEqual([0, 0]);
    expect(service.openEndPads({ type: 'circle', cx: 500, cy: 500, r: 50 }, board, 64)).toEqual([0, 0]);
  });
});
