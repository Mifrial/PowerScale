import type { MapPlacement } from '@/modules/Roleplay/Map/Dto/MapPlacement';

export interface MapNode {
  id: number;
  name: string;
  shortInfo: string;
  publicInfo?: string;
  gmInfo?: string;
  linkedMapId: number | null;
  icon: string;
  placements: MapPlacement[];
}
