import type { SaveSceneData } from '@/modules/Roleplay/Scene/Dto/SaveSceneData';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneFindPageQuery } from '@/modules/Roleplay/Scene/Dto/SceneFindPageQuery';
import type { ScenePage } from '@/modules/Roleplay/Scene/Dto/ScenePage';

/**
 * Контракт транспорта локаций `scene.*`.
 *
 * Запись — только полным снимком; промежуточных мутаций нет.
 */
export interface ISceneApi {
  findPage(query: SceneFindPageQuery, signal?: AbortSignal): Promise<ScenePage>;
  get(id: number, signal?: AbortSignal): Promise<SceneDetail>;
  save(data: SaveSceneData, signal?: AbortSignal): Promise<SceneDetail>;
  archive(id: number, signal?: AbortSignal): Promise<void>;
}
