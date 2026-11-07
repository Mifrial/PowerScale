import { describe, expect, it } from 'vitest';
import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import { MAP_DECOR_MAX_POINTS } from '@/modules/Roleplay/Map/Service/MapDecorService';
import { MapDecorService } from '@/modules/Roleplay/Map/Service/MapDecorService';
import { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';

const geometry = new MapGeometryService();
const service = new MapDecorService(geometry);

const square: MapPoint[] = [
  [0, 0],
  [400, 0],
  [400, 400],
  [0, 400],
];

function decor(overrides: Partial<MapAreaDecor> = {}): MapAreaDecor {
  return {
    set: 'tree',
    density: 25,
    scale: 100,
    jitter: 60,
    seed: 0,
    ...overrides,
  };
}

describe('MapDecorService', () => {
  it('lays out the same points for the same area, seed and settings', () => {
    const first = service.layout(1, square, decor());
    const second = service.layout(1, square, decor());
    const other = new MapDecorService(geometry).layout(1, square, decor());

    expect(second).toEqual(first);
    expect(other).toEqual(first);
    expect(first.length).toBeGreaterThan(100);
  });

  it('changes the layout when the seed changes', () => {
    const first = service.layout(2, square, decor({ seed: 1 }));
    const second = service.layout(2, square, decor({ seed: 2 }));

    expect(second).not.toEqual(first);
  });

  it('keeps every point inside the polygon', () => {
    const points = service.layout(3, square, decor());

    expect(points.length).toBeGreaterThan(20);
    for (const point of points) {
      expect(geometry.pointInPolygon([point.x, point.y], square)).toBe(true);
    }
  });

  it('returns nothing without a set, at zero density or for a degenerate polygon', () => {
    expect(service.layout(4, square, { density: 25 })).toEqual([]);
    expect(service.layout(5, square, decor({ density: 0 }))).toEqual([]);
    expect(
      service.layout(
        6,
        [
          [0, 0],
          [10, 0],
        ],
        decor(),
      ),
    ).toEqual([]);
  });

  it('caps the number of symbols for huge polygons', () => {
    const huge: MapPoint[] = [
      [0, 0],
      [4000, 0],
      [4000, 4000],
      [0, 4000],
    ];
    const points = service.layout(7, huge, decor({ density: 200, jitter: 0 }));

    expect(points.length).toBeLessThanOrEqual(MAP_DECOR_MAX_POINTS);
  });

  it('mixes symbols by their shares in a set', () => {
    const points = service.layout(8, square, decor({ set: 'mixed', density: 30, jitter: 90 }));
    const counts = { grass: 0, tree: 0, pine: 0, field: 0 };
    for (const point of points) {
      counts[point.kind] += 1;
    }

    expect(counts.grass).toBeGreaterThan(counts.tree);
    expect(counts.grass).toBeGreaterThan(counts.pine);
    expect(counts.tree).toBeGreaterThan(10);
    expect(counts.pine).toBeGreaterThan(10);
    expect(counts.field).toBe(0);
  });

  it('uses the set defaults when explicit values are absent', () => {
    const grass = service.layout(9, square, { set: 'grass' });
    const trees = service.layout(9, square, { set: 'tree' });

    expect(grass.length).toBeGreaterThan(trees.length);
  });

  it('clamps out-of-range settings instead of failing', () => {
    const points = service.layout(10, square, decor({ density: 500 }));

    expect(points.length).toBeGreaterThan(0);
    expect(points.length).toBeLessThanOrEqual(MAP_DECOR_MAX_POINTS);
  });
});
