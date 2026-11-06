import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapFindPageQuery } from '@/modules/Roleplay/Map/Dto/MapFindPageQuery';
import type { MapPage } from '@/modules/Roleplay/Map/Dto/MapPage';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';
import type { MapSaveResult } from '@/modules/Roleplay/Map/Dto/MapSaveResult';
import type { SaveMapData } from '@/modules/Roleplay/Map/Dto/SaveMapData';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';
import type { RouteMapData } from '@/modules/Roleplay/Map/Dto/RouteMapData';

/**
 * Контракт транспорта карт `map.*`.
 *
 * Запись — только публикацией полного снимка; промежуточных мутаций нет.
 */
export interface IMapApi {
  findPage(query: MapFindPageQuery, signal?: AbortSignal): Promise<MapPage>;
  get(id: number, signal?: AbortSignal): Promise<MapDetail>;
  getTerrains(signal?: AbortSignal): Promise<MapTerrain[]>;
  save(data: SaveMapData, signal?: AbortSignal): Promise<MapSaveResult>;
  archive(id: number, signal?: AbortSignal): Promise<void>;
  route(data: RouteMapData, signal?: AbortSignal): Promise<MapRoute>;
}
