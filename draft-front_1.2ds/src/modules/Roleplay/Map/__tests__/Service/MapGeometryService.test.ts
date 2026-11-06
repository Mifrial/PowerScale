import { describe, expect, it } from 'vitest';
import { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';

const service = new MapGeometryService();
const square: [number, number][] = [
  [0, 0],
  [10, 0],
  [10, 10],
  [0, 10],
];

describe('MapGeometryService', () => {
  it('measures polyline length', () => {
    expect(
      service.polylineLength([
        [0, 0],
        [3, 4],
        [3, 10],
      ]),
    ).toBe(11);
  });

  it('computes polygon area and centroid', () => {
    expect(service.polygonArea(square)).toBe(100);
    expect(service.polygonCentroid(square)).toEqual([5, 5]);
  });

  it('detects points inside and outside a polygon', () => {
    expect(service.pointInPolygon([5, 5], square)).toBe(true);
    expect(service.pointInPolygon([15, 5], square)).toBe(false);
    expect(service.pointInPolygon([-1, -1], square)).toBe(false);
  });

  it('measures distance to a segment with projection clamp', () => {
    expect(service.distanceToSegment([5, 5], [0, 0], [10, 0])).toBe(5);
    expect(service.distanceToSegment([-5, 0], [0, 0], [10, 0])).toBe(5);
    expect(service.distanceToSegment([15, 0], [0, 0], [10, 0])).toBe(5);
  });

  it('measures distance to a ribbon centre line minus half width', () => {
    const points: [number, number][] = [
      [0, 0],
      [20, 0],
    ];

    expect(service.distanceToRibbon([10, 12], points, [20, 20])).toBe(2);
    expect(service.distanceToRibbon([10, 4], points, [20, 20])).toBe(0);
    expect(service.distanceToRibbon([10, 4], points, [2])).toBe(Number.POSITIVE_INFINITY);
  });

  it('detects crossing and touching segments', () => {
    expect(service.segmentsIntersect([0, 0], [10, 10], [0, 10], [10, 0])).toBe(true);
    expect(service.segmentsIntersect([0, 0], [10, 0], [5, 0], [5, 10])).toBe(true);
    expect(service.segmentsIntersect([0, 0], [10, 0], [0, 5], [10, 5])).toBe(false);
  });

  it('flattens a bezier ribbon and interpolates widths', () => {
    const flat = service.flattenLine(
      [
        [0, 0],
        [100, 0],
      ],
      [
        { in: [0, 0], out: [0, 100] },
        { in: [0, 100], out: [0, 0] },
      ],
      [10, 20],
      4,
    );

    expect(flat.points.length).toBeGreaterThan(2);
    expect(flat.points[0]).toEqual([0, 0]);
    expect(flat.points[flat.points.length - 1]).toEqual([100, 0]);
    expect(flat.points[2][1]).toBeGreaterThan(0);
    expect(flat.widths[0]).toBe(10);
    expect(flat.widths[flat.widths.length - 1]).toBe(20);
  });

  it('splits a cubic without changing its endpoints', () => {
    const split = service.splitCubic([0, 0], [0, 100], [100, 100], [100, 0], 0.5);

    expect(split.left[0]).toEqual([0, 0]);
    expect(split.right[3]).toEqual([100, 0]);
    expect(split.left[3]).toEqual(split.right[0]);
    expect(split.left[3][1]).toBeGreaterThan(0);
  });

  it('shows stub handles for a straight node and real handles when set', () => {
    const points: [number, number][] = [
      [0, 0],
      [100, 0],
    ];
    const empty = service.lineDisplayHandles(points, [], 0, 20);
    expect(empty.inIsStub).toBe(true);
    expect(empty.outIsStub).toBe(true);
    expect(empty.out[0]).toBeGreaterThan(points[0][0]);

    const handles = [
      { in: [0, 0] as [number, number], out: [30, 10] as [number, number] },
      { in: [0, 0] as [number, number], out: [0, 0] as [number, number] },
    ];
    const real = service.lineDisplayHandles(points, handles, 0, 20);
    expect(real.outIsStub).toBe(false);
    expect(real.out).toEqual([30, 10]);
  });

  it('measures distance to the nearest polyline segment', () => {
    expect(
      service.distanceToPolyline(
        [5, 5],
        [
          [0, 0],
          [10, 0],
          [10, 10],
        ],
      ),
    ).toBe(5);
    expect(service.distanceToPolyline([5, 5], [])).toBe(Number.POSITIVE_INFINITY);
  });

  it('simplifies a staircase and keeps the endpoints', () => {
    const points: [number, number][] = [
      [0, 0],
      [5, 0],
      [10, 0],
      [10, 5],
      [10, 10],
    ];
    const simplified = service.simplifyPath(points, 3);

    expect(simplified[0]).toEqual([0, 0]);
    expect(simplified[simplified.length - 1]).toEqual([10, 10]);
    expect(simplified.length).toBeLessThan(points.length);
  });

  it('flattens a closed contour with handles', () => {
    expect(service.flattenContour(square, [])).toEqual(square);

    const handles = [
      { in: [0, 0] as [number, number], out: [0, 4] as [number, number] },
      { in: [0, 4] as [number, number], out: [0, 0] as [number, number] },
      { in: [0, 0] as [number, number], out: [0, 0] as [number, number] },
      { in: [0, 0] as [number, number], out: [0, 0] as [number, number] },
    ];
    const flat = service.flattenContour(square, handles, 4);

    expect(flat).toHaveLength(16);
    expect(flat[0]).toEqual([0, 0]);
    expect(flat[1][1]).toBeGreaterThan(0);
    expect(flat[4]).toEqual([10, 0]);
    expect(flat[12]).toEqual([0, 10]);
    expect(service.pointInPolygon([5, 6], flat)).toBe(true);
    expect(service.pointInPolygon([5, 0.1], flat)).toBe(false);
  });

  it('shows stub handles around a closed contour', () => {
    const display = service.areaDisplayHandles(square, [], 0, 2);

    expect(display.inIsStub).toBe(true);
    expect(display.outIsStub).toBe(true);
    expect(Math.hypot(display.in[0], display.in[1])).toBeCloseTo(2);
    expect(display.in[0]).toBeCloseTo(-display.out[0]);
    expect(display.in[1]).toBeCloseTo(-display.out[1]);
  });

  it('smooths a path without moving the endpoints', () => {
    const points: [number, number][] = [
      [0, 0],
      [0, 10],
      [10, 10],
    ];
    const smooth = service.smoothPath(points);

    expect(smooth[0]).toEqual([0, 0]);
    expect(smooth[smooth.length - 1]).toEqual([10, 10]);
    expect(smooth.length).toBeGreaterThan(points.length);
    for (const point of smooth) {
      expect(point[0]).toBeGreaterThanOrEqual(0);
      expect(point[0]).toBeLessThanOrEqual(10);
      expect(point[1]).toBeGreaterThanOrEqual(0);
      expect(point[1]).toBeLessThanOrEqual(10);
    }
  });
});
