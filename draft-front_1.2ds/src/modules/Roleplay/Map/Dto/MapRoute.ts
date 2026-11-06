import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

export interface MapRoute {
  nodeIds: number[];
  points: MapPoint[];
  edgeIds: number[];
  distanceMeters: number;
  costMeters: number;
}
