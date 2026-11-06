import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

export interface MapAreaShape {
  variantId: number;
  path: MapPoint[];
  isPresent: boolean;
}
