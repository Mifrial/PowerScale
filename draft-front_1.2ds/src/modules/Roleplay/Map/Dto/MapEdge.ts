import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

export interface MapEdge {
  id: number;
  variantId: number;
  fromNodeId: number;
  toNodeId: number;
  terrainCode: string;
  path: MapPoint[];
  distanceMeters: number | null;
  isDirected: boolean;
  label: string;
}
