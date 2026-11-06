import type { MapLineHandle } from '@/modules/Roleplay/Map/Dto/MapLineHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

export interface MapLine {
  id: number;
  variantId: number;
  terrainCode: string;
  points: MapPoint[];
  widths: number[];
  handles: MapLineHandle[];
  label: string;
  isPresent: boolean;
}
