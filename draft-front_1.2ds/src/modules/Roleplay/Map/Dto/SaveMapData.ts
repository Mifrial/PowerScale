import type { MapSnapshot } from '@/modules/Roleplay/Map/Dto/MapSnapshot';

/** Тело `map.save`: снимок и токен версии. */
export interface SaveMapData {
  id?: number;
  expectedVersion?: number;
  force?: boolean;
  map: MapSnapshot;
}
