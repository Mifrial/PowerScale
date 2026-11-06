import type { MapSummary } from '@/modules/Roleplay/Map/Dto/MapSummary';

export interface MapPage {
  items: MapSummary[];
  total: number;
}
