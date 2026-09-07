import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IKeywordApi } from '@/modules/Roleplay/Keyword/Interface/IKeywordApi';
import type { CreateKeywordData } from '@/modules/Roleplay/Keyword/Dto/CreateKeywordData';
import type { UpdateKeywordData } from '@/modules/Roleplay/Keyword/Dto/UpdateKeywordData';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';

/**
 * Транспорт справочника признаков.
 */
export class KeywordApi implements IKeywordApi {
  constructor(private readonly engine: Engine) {}

  async getKeywords(signal?: AbortSignal): Promise<Keyword[]> {
    const res = await this.engine.runAction<Keyword[]>('keyword.getList', undefined, signal);

    return res.data ?? [];
  }

  async getKeyword(id: number, signal?: AbortSignal): Promise<Keyword> {
    const res = await this.engine.runAction<Keyword>('keyword.get', { id }, signal);
    if (!res.data) throw new Error('Признак не найден');

    return res.data;
  }

  async createKeyword(data: CreateKeywordData, signal?: AbortSignal): Promise<Keyword> {
    const res = await this.engine.runAction<Keyword>(
      'keyword.create',
      { code: data.code, name: data.name, description: data.description ?? '' },
      signal,
    );
    if (!res.data) throw new Error('Failed to create keyword');

    return res.data;
  }

  async updateKeyword(id: number, data: UpdateKeywordData, signal?: AbortSignal): Promise<Keyword> {
    const res = await this.engine.runAction<Keyword>('keyword.update', { id, ...data }, signal);
    if (!res.data) throw new Error('Failed to update keyword');

    return res.data;
  }

  async deactivate(id: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('keyword.deactivate', { id }, signal);
  }
}
