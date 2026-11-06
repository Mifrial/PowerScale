import type { MapLineHandle } from '@/modules/Roleplay/Map/Dto/MapLineHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/** Кандидат hit-test ленты: id, осевая линия, ручки и ширины. */
export interface MapLineHitCandidate {
  id: number;
  points: MapPoint[];
  widths: number[];
  handles: MapLineHandle[];
  sortOrder?: number;
}
