import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { SaveSceneData } from '@/modules/Roleplay/Scene/Dto/SaveSceneData';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneFindPageQuery } from '@/modules/Roleplay/Scene/Dto/SceneFindPageQuery';
import type { ScenePage } from '@/modules/Roleplay/Scene/Dto/ScenePage';
import type { ISceneApi } from '@/modules/Roleplay/Scene/Interface/ISceneApi';
import { SceneApiError } from '@/modules/Roleplay/Scene/Service/SceneApiError';

/**
 * HTTP-клиент локаций: каталог, снимок, сохранение и архив.
 */
export class SceneApi implements ISceneApi {
  constructor(private readonly engine: Engine) {}

  async findPage(query: SceneFindPageQuery, signal?: AbortSignal): Promise<ScenePage> {
    const payload: Record<string, unknown> = { limit: query.limit, offset: query.offset };
    if (query.q !== undefined) payload.q = query.q;
    if (query.isArchived !== undefined) payload.isArchived = query.isArchived;

    return this.requireData<ScenePage>('scene.findPage', payload, signal);
  }

  async get(id: number, signal?: AbortSignal): Promise<SceneDetail> {
    return this.requireData<SceneDetail>('scene.get', { id }, signal);
  }

  async save(data: SaveSceneData, signal?: AbortSignal): Promise<SceneDetail> {
    const payload: Record<string, unknown> = { scene: data.scene };
    if (data.id !== undefined) payload.id = data.id;
    if (data.expectedVersion !== undefined) payload.expectedVersion = data.expectedVersion;

    return this.requireData<SceneDetail>('scene.save', payload, signal);
  }

  async archive(id: number, signal?: AbortSignal): Promise<void> {
    await this.requireSuccess('scene.archive', { id }, signal);
  }

  private async requireData<T>(action: string, payload: unknown, signal?: AbortSignal): Promise<T> {
    const response = await this.engine.runAction<T>(action, payload, signal);
    if (response.error) {
      throw SceneApiError.fromActionError(response.error);
    }

    if (response.data === null || response.data === undefined) {
      throw new SceneApiError('SCENE_EMPTY_RESPONSE', 'Сервер не вернул данные локации');
    }

    return response.data;
  }

  private async requireSuccess(action: string, payload: unknown, signal?: AbortSignal): Promise<void> {
    const response = await this.engine.runAction(action, payload, signal);
    if (response.error) {
      throw SceneApiError.fromActionError(response.error);
    }
  }
}
