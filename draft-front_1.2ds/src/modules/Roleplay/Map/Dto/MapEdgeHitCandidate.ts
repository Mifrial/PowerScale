import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/** Кандидат hit-test ребра: id и полилиния. */
export interface MapEdgeHitCandidate {
  id: number;
  path: MapPoint[];
}
