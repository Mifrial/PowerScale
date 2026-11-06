import { describe, expect, it } from 'vitest';
import type { MapHitCandidates } from '@/modules/Roleplay/Map/Dto/MapHitCandidates';
import { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';
import { MapHitTestService } from '@/modules/Roleplay/Map/Service/MapHitTestService';

const service = new MapHitTestService(new MapGeometryService());
const tolerances = { node: 5, edge: 3 };

function candidates(overrides: Partial<MapHitCandidates> = {}): MapHitCandidates {
  return {
    nodes: [],
    objects: [],
    terrains: [],
    lines: [],
    edges: [],
    ...overrides,
  };
}

describe('MapHitTestService', () => {
  it('finds the nearest node within tolerance', () => {
    const nodes = [
      { id: 1, x: 10, y: 10 },
      { id: 2, x: 30, y: 10 },
    ];

    expect(service.findNode([12, 12], nodes, 5)).toEqual({ kind: 'node', id: 1 });
    expect(service.findNode([100, 100], nodes, 5)).toBeNull();
  });

  it('finds the topmost area containing the point', () => {
    const areas = [
      {
        id: 1,
        path: [
          [0, 0],
          [20, 0],
          [20, 20],
          [0, 20],
        ] as [number, number][],
      },
      {
        id: 2,
        path: [
          [5, 5],
          [15, 5],
          [15, 15],
          [5, 15],
        ] as [number, number][],
      },
    ];

    expect(service.findArea([10, 10], areas)).toEqual({ kind: 'area', id: 2 });
    expect(service.findArea([25, 10], areas)).toBeNull();
  });

  it('hits a ribbon by its half width', () => {
    const lines = [
      {
        id: 3,
        points: [
          [0, 0],
          [20, 0],
        ] as [number, number][],
        widths: [10, 10],
        handles: [
          { in: [0, 0] as [number, number], out: [0, 0] as [number, number] },
          { in: [0, 0] as [number, number], out: [0, 0] as [number, number] },
        ],
      },
    ];

    expect(service.findLine([10, 4], lines, 0)).toEqual({ kind: 'line', id: 3 });
    expect(service.findLine([10, 8], lines, 0)).toBeNull();
  });

  it('prefers a node over an object, a line, an edge and terrain', () => {
    const hitCandidates = candidates({
      nodes: [{ id: 5, x: 10, y: 10 }],
      objects: [
        {
          id: 6,
          path: [
            [0, 0],
            [20, 0],
            [20, 20],
            [0, 20],
          ],
        },
      ],
      lines: [
        {
          id: 8,
          points: [
            [0, 10],
            [20, 10],
          ],
          widths: [6, 6],
          handles: [
            { in: [0, 0] as [number, number], out: [0, 0] as [number, number] },
            { in: [0, 0] as [number, number], out: [0, 0] as [number, number] },
          ],
        },
      ],
      edges: [
        {
          id: 7,
          path: [
            [0, 10],
            [20, 10],
          ],
        },
      ],
      terrains: [
        {
          id: 9,
          path: [
            [0, 0],
            [30, 0],
            [30, 30],
            [0, 30],
          ],
        },
      ],
    });

    expect(service.hitTest([10, 10], hitCandidates, tolerances)).toEqual({ kind: 'node', id: 5 });
    expect(service.hitTest([2, 2], { ...hitCandidates, nodes: [] }, tolerances)).toEqual({
      kind: 'area',
      id: 6,
    });
    expect(service.hitTest([30, 10], { ...hitCandidates, nodes: [], objects: [] }, tolerances)).toBeNull();
  });

  it('falls back to a line, then an edge, then terrain', () => {
    const line = {
      id: 8,
      points: [
        [0, 10],
        [20, 10],
      ] as [number, number][],
      widths: [6, 6],
      handles: [
        { in: [0, 0] as [number, number], out: [0, 0] as [number, number] },
        { in: [0, 0] as [number, number], out: [0, 0] as [number, number] },
      ],
    };
    const edge = {
      id: 7,
      path: [
        [0, 20],
        [20, 20],
      ] as [number, number][],
    };
    const terrain = {
      id: 9,
      path: [
        [0, 0],
        [30, 0],
        [30, 30],
        [0, 30],
      ] as [number, number][],
    };
    const base = candidates({ lines: [line], edges: [edge], terrains: [terrain] });

    expect(service.hitTest([10, 12], base, tolerances)).toEqual({ kind: 'line', id: 8 });
    expect(service.hitTest([10, 21], { ...base, lines: [] }, tolerances)).toEqual({ kind: 'edge', id: 7 });
    expect(service.hitTest([25, 5], { ...base, lines: [], edges: [] }, tolerances)).toEqual({
      kind: 'area',
      id: 9,
    });
  });
});
