import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

export interface MapRoute {
  points: MapPoint[];
  distancePixels: number;
  costPixels: number;
  distanceMeters: number | null;
  costMeters: number | null;
}
