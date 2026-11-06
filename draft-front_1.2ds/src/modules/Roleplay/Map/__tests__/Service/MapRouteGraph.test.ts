import { describe, expect, it } from 'vitest';
import type { MapEdge } from '@/modules/Roleplay/Map/Dto/MapEdge';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';
import { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';
import { MapRouteGraph } from '@/modules/Roleplay/Map/Service/MapRouteGraph';

const graph = new MapRouteGraph(new MapGeometryService());

const terrains: MapTerrain[] = [
  { id: 1, code: 'road', label: 'Дорога', speedFactor: 100, color: '#aaa', sortOrder: 1 },
  { id: 2, code: 'path', label: 'Тропа', speedFactor: 50, color: '#bbb', sortOrder: 2 },
  { id: 3, code: 'water', label: 'Вода', speedFactor: 0, color: '#ccc', sortOrder: 3 },
];

const placements: Record<number, MapPoint> = {
  1: [0, 0],
  2: [100, 0],
};

function edge(overrides: Partial<MapEdge> = {}): MapEdge {
  return {
    id: 10,
    variantId: 1,
    fromNodeId: 1,
    toNodeId: 2,
    terrainCode: 'road',
    path: [],
    distanceMeters: 100,
    isDirected: false,
    label: '',
    ...overrides,
  };
}

function expectCode(action: () => void, code: string): void {
  try {
    action();
    throw new Error('expected an error');
  } catch (caught) {
    expect(caught).toMatchObject({ code });
  }
}

describe('MapRouteGraph', () => {
  it('picks the cheaper of parallel edges', () => {
    const adjacency = graph.buildAdjacency(
      [edge({ id: 10, terrainCode: 'road' }), edge({ id: 11, terrainCode: 'path', distanceMeters: 80 })],
      placements,
      terrains,
      1000,
    );
    const path = graph.shortestPath(adjacency, 1, 2);

    expect(path.edgeIds).toEqual([10]);
    expect(path.distance).toBe(100);
    expect(path.cost).toBe(100);
  });

  it('skips terrain that blocks passage', () => {
    expectCode(
      () =>
        graph.shortestPath(graph.buildAdjacency([edge({ terrainCode: 'water' })], placements, terrains, 1000), 1, 2),
      'MAP_NO_ROUTE',
    );
  });

  it('does not traverse a directed edge backwards', () => {
    const adjacency = graph.buildAdjacency([edge({ isDirected: true })], placements, terrains, 1000);

    expectCode(() => graph.shortestPath(adjacency, 2, 1), 'MAP_NO_ROUTE');
  });

  it('measures distance by the variant scale', () => {
    const adjacency = graph.buildAdjacency(
      [
        edge({
          path: [
            [0, 0],
            [1000, 0],
          ],
          distanceMeters: null,
        }),
      ],
      placements,
      terrains,
      1000,
    );

    expect(adjacency[1][0].distance).toBe(1000);
  });

  it('requires a scale when an edge has no explicit distance', () => {
    expectCode(() => graph.buildAdjacency([edge({ distanceMeters: null })], placements, terrains, null), 'MAP_INVALID');
  });

  it('builds route points from an empty edge path', () => {
    const route = graph.findRoute([edge()], placements, terrains, 1000, 1, 2);

    expect(route.nodeIds).toEqual([1, 2]);
    expect(route.edgeIds).toEqual([10]);
    expect(route.points).toEqual([
      [0, 0],
      [100, 0],
    ]);
  });
});
