import type { Engine } from '@/modules/Core/Engine/Service/Engine'
import type { IKeywordApi, CreateKeywordData, UpdateKeywordData } from '@/modules/Roleplay/Rule/Interface/IKeywordApi'
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword'

export class KeywordApi implements IKeywordApi {
  constructor(private readonly engine: Engine) {}

  async getTags(signal?: AbortSignal): Promise<Keyword[]> {
    const res = await this.engine.runAction<Keyword[]>('keyword.getList', undefined, signal)
    return res.data ?? []
  }

  async getTag(id: number, signal?: AbortSignal): Promise<Keyword> {
    const res = await this.engine.runAction<Keyword>('keyword.get', { id }, signal)
    if (!res.data) throw new Error('Keyword not found')
    return res.data
  }

  async createTag(data: CreateKeywordData, signal?: AbortSignal): Promise<Keyword> {
    const res = await this.engine.runAction<Keyword>('keyword.create', data, signal)
    if (!res.data) throw new Error('Failed to create keyword')
    return res.data
  }

  async updateTag(id: number, data: UpdateKeywordData, signal?: AbortSignal): Promise<Keyword> {
    const res = await this.engine.runAction<Keyword>('keyword.update', { id, ...data }, signal)
    if (!res.data) throw new Error('Failed to update keyword')
    return res.data
  }

  async deactivateTag(id: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('keyword.deactivate', { id }, signal)
  }
}
