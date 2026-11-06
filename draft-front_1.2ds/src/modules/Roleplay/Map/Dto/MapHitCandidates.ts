import type { MapAreaHitCandidate } from '@/modules/Roleplay/Map/Dto/MapAreaHitCandidate';
import type { MapLineHitCandidate } from '@/modules/Roleplay/Map/Dto/MapLineHitCandidate';
import type { MapNodeHitCandidate } from '@/modules/Roleplay/Map/Dto/MapNodeHitCandidate';

/** Набор объектов карты для поиска под указателем. */
export interface MapHitCandidates {
  nodes: MapNodeHitCandidate[];
  objects: MapAreaHitCandidate[];
  surfaces: MapAreaHitCandidate[];
  lines: MapLineHitCandidate[];
}
