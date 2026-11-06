import type { MapAreaHitCandidate } from '@/modules/Roleplay/Map/Dto/MapAreaHitCandidate';
import type { MapEdgeHitCandidate } from '@/modules/Roleplay/Map/Dto/MapEdgeHitCandidate';
import type { MapHit } from '@/modules/Roleplay/Map/Dto/MapHit';
import type { MapHitCandidates } from '@/modules/Roleplay/Map/Dto/MapHitCandidates';
import type { MapHitTolerances } from '@/modules/Roleplay/Map/Dto/MapHitTolerances';
import type { MapLineHitCandidate } from '@/modules/Roleplay/Map/Dto/MapLineHitCandidate';
import type { MapNodeHitCandidate } from '@/modules/Roleplay/Map/Dto/MapNodeHitCandidate';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';

/**
 * Поиск объекта карты под указателем.
 *
 * Приоритет: узел, объект, лента, ребро, ландшафт. Лента попадает и по ширине.
 */
export class MapHitTestService {
  constructor(private readonly geometry: MapGeometryService) {}

  findNode(point: MapPoint, nodes: MapNodeHitCandidate[], tolerance: number): MapHit | null {
    let nearest: MapNodeHitCandidate | null = null;
    let nearestDistance = tolerance;
    for (const node of nodes) {
      const distance = this.geometry.distance(point, [node.x, node.y]);
      if (distance <= nearestDistance) {
        nearest = node;
        nearestDistance = distance;
      }
    }

    return nearest === null ? null : { kind: 'node', id: nearest.id };
  }

  findArea(point: MapPoint, areas: MapAreaHitCandidate[]): MapHit | null {
    for (let index = areas.length - 1; index >= 0; index -= 1) {
      if (this.geometry.pointInPolygon(point, areas[index].path)) {
        return { kind: 'area', id: areas[index].id };
      }
    }

    return null;
  }

  findLine(point: MapPoint, lines: MapLineHitCandidate[], tolerance: number): MapHit | null {
    let nearest: MapLineHitCandidate | null = null;
    let nearestDistance = tolerance;
    for (const line of lines) {
      const flat = this.geometry.flattenLine(line.points, line.handles ?? [], line.widths);
      const distance = this.geometry.distanceToRibbon(point, flat.points, flat.widths);
      if (distance <= nearestDistance) {
        nearest = line;
        nearestDistance = distance;
      }
    }

    return nearest === null ? null : { kind: 'line', id: nearest.id };
  }

  findEdge(point: MapPoint, edges: MapEdgeHitCandidate[], tolerance: number): MapHit | null {
    let nearest: MapEdgeHitCandidate | null = null;
    let nearestDistance = tolerance;
    for (const edge of edges) {
      const distance = this.geometry.distanceToPolyline(point, edge.path);
      if (distance <= nearestDistance) {
        nearest = edge;
        nearestDistance = distance;
      }
    }

    return nearest === null ? null : { kind: 'edge', id: nearest.id };
  }

  hitTest(point: MapPoint, candidates: MapHitCandidates, tolerances: MapHitTolerances): MapHit | null {
    return (
      this.findNode(point, candidates.nodes, tolerances.node) ??
      this.findArea(point, candidates.objects) ??
      this.findLine(point, candidates.lines, tolerances.edge) ??
      this.findEdge(point, candidates.edges, tolerances.edge) ??
      this.findArea(point, candidates.terrains)
    );
  }
}
