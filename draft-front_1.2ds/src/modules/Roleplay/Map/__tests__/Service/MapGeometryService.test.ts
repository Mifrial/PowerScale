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

  it('resolves an empty edge path to the straight line between placements', () => {
    const edge = {
      id: 1,
      variantId: 1,
      fromNodeId: 1,
      toNodeId: 2,
      terrainCode: 'road',
      path: [],
      distanceMeters: null,
      isDirected: false,
      label: '',
    };
    const points = service.edgePoints(edge, { 1: [0, 0], 2: [10, 0] });

    expect(points).toEqual([
      [0, 0],
      [10, 0],
    ]);
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
});
