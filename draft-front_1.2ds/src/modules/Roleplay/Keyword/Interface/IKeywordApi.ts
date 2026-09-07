import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { CreateKeywordData } from '@/modules/Roleplay/Keyword/Dto/CreateKeywordData';
import type { UpdateKeywordData } from '@/modules/Roleplay/Keyword/Dto/UpdateKeywordData';

export interface IKeywordApi {
  getKeywords(signal?: AbortSignal): Promise<Keyword[]>;
  getKeyword(id: number, signal?: AbortSignal): Promise<Keyword>;
  createKeyword(data: CreateKeywordData, signal?: AbortSignal): Promise<Keyword>;
  updateKeyword(id: number, data: UpdateKeywordData, signal?: AbortSignal): Promise<Keyword>;
  deactivate(id: number, signal?: AbortSignal): Promise<void>;
}
