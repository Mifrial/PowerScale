import type { MapAreaHitCandidate } from '@/modules/Roleplay/Map/Dto/MapAreaHitCandidate';
import type { MapEdgeHitCandidate } from '@/modules/Roleplay/Map/Dto/MapEdgeHitCandidate';
import type { MapLineHitCandidate } from '@/modules/Roleplay/Map/Dto/MapLineHitCandidate';
import type { MapNodeHitCandidate } from '@/modules/Roleplay/Map/Dto/MapNodeHitCandidate';

/** Набор объектов карты для поиска под указателем. */
export interface MapHitCandidates {
  nodes: MapNodeHitCandidate[];
  objects: MapAreaHitCandidate[];
  terrains: MapAreaHitCandidate[];
  lines: MapLineHitCandidate[];
  edges: MapEdgeHitCandidate[];
}
