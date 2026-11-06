import type { MapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/** Кандидат hit-test области: id, контур и ручки. */
export interface MapAreaHitCandidate {
  id: number;
  path: MapPoint[];
  /** Ручки по вершинам; пусто или нет — прямые рёбра. */
  handles?: MapHandle[];
  sortOrder?: number;
}
