import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';

export interface CreateKeywordData {
  code: string;
  name: string;
  description?: string;
}

export interface UpdateKeywordData {
  code?: string;
  name?: string;
  description?: string;
  active?: boolean;
}

export interface IKeywordApi {
  getTags(signal?: AbortSignal): Promise<Keyword[]>;
  getTag(id: number, signal?: AbortSignal): Promise<Keyword>;
  createTag(data: CreateKeywordData, signal?: AbortSignal): Promise<Keyword>;
  updateTag(id: number, data: UpdateKeywordData, signal?: AbortSignal): Promise<Keyword>;
  deactivateTag(id: number, signal?: AbortSignal): Promise<void>;
}
