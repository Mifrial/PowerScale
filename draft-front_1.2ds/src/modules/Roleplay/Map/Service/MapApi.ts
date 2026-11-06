import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IMapApi } from '@/modules/Roleplay/Map/Interface/IMapApi';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapFindPageQuery } from '@/modules/Roleplay/Map/Dto/MapFindPageQuery';
import type { MapPage } from '@/modules/Roleplay/Map/Dto/MapPage';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';
import type { MapSaveResult } from '@/modules/Roleplay/Map/Dto/MapSaveResult';
import type { SaveMapData } from '@/modules/Roleplay/Map/Dto/SaveMapData';
import type { RouteMapData } from '@/modules/Roleplay/Map/Dto/RouteMapData';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';

/**
 * HTTP-клиент карт: каталог, снимок, публикация, архив и маршрут.
 */
export class MapApi implements IMapApi {
  constructor(private readonly engine: Engine) {}

  async findPage(query: MapFindPageQuery, signal?: AbortSignal): Promise<MapPage> {
    const payload: Record<string, unknown> = { limit: query.limit, offset: query.offset };
    if (query.q !== undefined) payload.q = query.q;
    if (query.ownerId !== undefined) payload.ownerId = query.ownerId;
    if (query.isArchived !== undefined) payload.isArchived = query.isArchived;

    return this.requireData<MapPage>('map.findPage', payload, signal);
  }

  async get(id: number, signal?: AbortSignal): Promise<MapDetail> {
    return this.requireData<MapDetail>('map.get', { id }, signal);
  }

  async save(data: SaveMapData, signal?: AbortSignal): Promise<MapSaveResult> {
    const payload: Record<string, unknown> = { map: data.map };
    if (data.id !== undefined) payload.id = data.id;
    if (data.expectedVersion !== undefined) payload.expectedVersion = data.expectedVersion;
    if (data.force !== undefined) payload.force = data.force;

    return this.requireData<MapSaveResult>('map.save', payload, signal);
  }

  async archive(id: number, signal?: AbortSignal): Promise<void> {
    await this.requireSuccess('map.archive', { id }, signal);
  }

  async route(data: RouteMapData, signal?: AbortSignal): Promise<MapRoute> {
    return this.requireData<MapRoute>('map.route', { ...data }, signal);
  }

  private async requireData<T>(action: string, payload: unknown, signal?: AbortSignal): Promise<T> {
    const response = await this.engine.runAction<T>(action, payload, signal);
    if (response.error) {
      throw MapApiError.fromActionError(response.error);
    }

    if (response.data === null || response.data === undefined) {
      throw new MapApiError('MAP_EMPTY_RESPONSE', 'Сервер не вернул данные карты');
    }

    return response.data;
  }

  private async requireSuccess(action: string, payload: unknown, signal?: AbortSignal): Promise<void> {
    const response = await this.engine.runAction(action, payload, signal);
    if (response.error) {
      throw MapApiError.fromActionError(response.error);
    }
  }
}
