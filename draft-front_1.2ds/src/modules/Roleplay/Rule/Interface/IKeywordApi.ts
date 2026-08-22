import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { CreateKeywordData } from '@/modules/Roleplay/Rule/Dto/CreateKeywordData';
import type { UpdateKeywordData } from '@/modules/Roleplay/Rule/Dto/UpdateKeywordData';

export interface IKeywordApi {
  getTags(signal?: AbortSignal): Promise<Keyword[]>;
  getTag(id: number, signal?: AbortSignal): Promise<Keyword>;
  createTag(data: CreateKeywordData, signal?: AbortSignal): Promise<Keyword>;
  updateTag(id: number, data: UpdateKeywordData, signal?: AbortSignal): Promise<Keyword>;
  deactivateTag(id: number, signal?: AbortSignal): Promise<void>;
}
