import type { MapEdge } from '@/modules/Roleplay/Map/Dto/MapEdge';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';
import type { MapRouteAdjacency } from '@/modules/Roleplay/Map/Dto/MapRouteAdjacency';
import type { MapRouteLink } from '@/modules/Roleplay/Map/Dto/MapRouteLink';
import type { MapRoutePath } from '@/modules/Roleplay/Map/Dto/MapRoutePath';
import type { MapRouteStep } from '@/modules/Roleplay/Map/Dto/MapRouteStep';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';
import type { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';

/**
 * Чистый граф маршрута: смежность, Dijkstra, расстояние и геометрия пути.
 *
 * Зеркало серверного `MapRouteGraph`, чтобы редактор считал без запроса.
 */
export class MapRouteGraph {
  constructor(private readonly geometry: MapGeometryService) {}

  buildAdjacency(
    edges: MapEdge[],
    placements: Record<number, MapPoint>,
    terrains: MapTerrain[],
    millimetersPerPixel: number | null,
  ): MapRouteAdjacency {
    const terrainByCode = new Map(terrains.map((terrain) => [terrain.code, terrain]));
    const adjacency: MapRouteAdjacency = {};

    for (const edge of edges) {
      const terrain = terrainByCode.get(edge.terrainCode);
      if (!terrain || terrain.speedFactor <= 0) {
        continue;
      }
      if (!placements[edge.fromNodeId] || !placements[edge.toNodeId]) {
        continue;
      }

      const distance = this.edgeDistance(edge, placements, millimetersPerPixel);
      const cost = Math.round((distance * 100) / terrain.speedFactor);
      this.appendLink(adjacency, edge.fromNodeId, edge.toNodeId, edge.id, distance, cost);
      if (!edge.isDirected) {
        this.appendLink(adjacency, edge.toNodeId, edge.fromNodeId, edge.id, distance, cost);
      }
    }

    return adjacency;
  }

  shortestPath(adjacency: MapRouteAdjacency, fromNodeId: number, toNodeId: number): MapRoutePath {
    const costs = new Map<number, number>([[fromNodeId, 0]]);
    const distances = new Map<number, number>([[fromNodeId, 0]]);
    const previous = new Map<number, MapRouteStep>();
    const unvisited = new Set<number>([fromNodeId]);

    while (unvisited.size > 0) {
      const current = this.nearestNode(unvisited, costs);
      if (current === null) {
        break;
      }

      unvisited.delete(current);
      if (current === toNodeId) {
        break;
      }

      this.relax(adjacency[current] ?? [], current, costs, distances, previous, unvisited);
    }

    if (!costs.has(toNodeId)) {
      throw new MapApiError('MAP_NO_ROUTE', 'Между точками нет проходимого маршрута');
    }

    return this.reconstruct(toNodeId, previous, distances, costs);
  }

  /** Полный маршрут с точками для отрисовки. */
  findRoute(
    edges: MapEdge[],
    placements: Record<number, MapPoint>,
    terrains: MapTerrain[],
    millimetersPerPixel: number | null,
    fromNodeId: number,
    toNodeId: number,
  ): MapRoute {
    const adjacency = this.buildAdjacency(edges, placements, terrains, millimetersPerPixel);
    const path = this.shortestPath(adjacency, fromNodeId, toNodeId);

    return {
      nodeIds: path.nodeIds,
      edgeIds: path.edgeIds,
      points: this.pathPoints(path, edges, placements),
      distanceMeters: path.distance,
      costMeters: path.cost,
    };
  }

  private appendLink(
    adjacency: MapRouteAdjacency,
    fromNodeId: number,
    toNodeId: number,
    edgeId: number,
    distance: number,
    cost: number,
  ): void {
    const links = adjacency[fromNodeId] ?? [];
    const link: MapRouteLink = { to: toNodeId, edgeId, distance, cost };
    adjacency[fromNodeId] = [...links, link];
  }

  private relax(
    links: MapRouteLink[],
    current: number,
    costs: Map<number, number>,
    distances: Map<number, number>,
    previous: Map<number, MapRouteStep>,
    unvisited: Set<number>,
  ): void {
    for (const link of links) {
      const next = link.to;
      const currentCost = costs.get(current) ?? 0;
      const candidateCost = currentCost + link.cost;
      const knownCost = costs.get(next);
      if (knownCost !== undefined && knownCost <= candidateCost) {
        continue;
      }

      costs.set(next, candidateCost);
      distances.set(next, (distances.get(current) ?? 0) + link.distance);
      previous.set(next, { nodeId: current, edgeId: link.edgeId });
      unvisited.add(next);
    }
  }

  private nearestNode(unvisited: Set<number>, costs: Map<number, number>): number | null {
    let nearest: number | null = null;
    let best = Number.POSITIVE_INFINITY;
    for (const nodeId of unvisited) {
      const cost = costs.get(nodeId);
      if (cost !== undefined && cost < best) {
        best = cost;
        nearest = nodeId;
      }
    }

    return nearest;
  }

  private reconstruct(
    toNodeId: number,
    previous: Map<number, MapRouteStep>,
    distances: Map<number, number>,
    costs: Map<number, number>,
  ): MapRoutePath {
    const nodeIds = [toNodeId];
    const edgeIds: number[] = [];
    let current = toNodeId;
    let step = previous.get(current);
    while (step) {
      edgeIds.push(step.edgeId);
      current = step.nodeId;
      nodeIds.push(current);
      step = previous.get(current);
    }

    return {
      nodeIds: nodeIds.reverse(),
      edgeIds: edgeIds.reverse(),
      distance: distances.get(toNodeId) ?? 0,
      cost: costs.get(toNodeId) ?? 0,
    };
  }

  private pathPoints(path: MapRoutePath, edges: MapEdge[], placements: Record<number, MapPoint>): MapPoint[] {
    const points: MapPoint[] = [];
    let currentNodeId = path.nodeIds[0];
    for (const edgeId of path.edgeIds) {
      const edge = edges.find((candidate) => candidate.id === edgeId);
      if (!edge) {
        continue;
      }

      const edgePoints = this.geometry.edgePoints(edge, placements);
      const ordered = edge.fromNodeId === currentNodeId ? edgePoints : [...edgePoints].reverse();
      for (const point of ordered) {
        const last = points[points.length - 1];
        if (!last || last[0] !== point[0] || last[1] !== point[1]) {
          points.push(point);
        }
      }

      currentNodeId = edge.fromNodeId === currentNodeId ? edge.toNodeId : edge.fromNodeId;
    }

    return points;
  }

  private edgeDistance(
    edge: MapEdge,
    placements: Record<number, MapPoint>,
    millimetersPerPixel: number | null,
  ): number {
    if (edge.distanceMeters !== null) {
      return edge.distanceMeters;
    }

    if (millimetersPerPixel === null) {
      throw new MapApiError('MAP_INVALID', 'Масштаб карты не задан');
    }

    const points = this.geometry.edgePoints(edge, placements);
    const pixels = this.geometry.polylineLength(points);

    return Math.round((pixels * millimetersPerPixel) / 1000);
  }
}
