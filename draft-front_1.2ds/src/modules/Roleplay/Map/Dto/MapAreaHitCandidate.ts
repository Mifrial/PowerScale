import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/** Кандидат hit-test области: id и полигон. */
export interface MapAreaHitCandidate {
  id: number;
  path: MapPoint[];
}
