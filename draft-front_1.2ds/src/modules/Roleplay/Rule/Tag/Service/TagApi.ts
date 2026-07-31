import type { Engine } from '@/modules/Core/Engine/Action/Engine'
import type { ITagApi, CreateTagData, UpdateTagData } from '../Interface/ITagApi'
import type { Tag } from '../Interface/types'

export class TagApi implements ITagApi {
  constructor(private engine: Engine) {}

  async getTags(signal?: AbortSignal): Promise<Tag[]> {
    const res = await this.engine.runAction<Tag[]>('tag.getList', undefined, signal)
    return res.data ?? []
  }

  async getTag(id: number, signal?: AbortSignal): Promise<Tag> {
    const res = await this.engine.runAction<Tag>('tag.get', { id }, signal)
    if (!res.data) throw new Error('Tag not found')
    return res.data
  }

  async createTag(data: CreateTagData, signal?: AbortSignal): Promise<Tag> {
    const res = await this.engine.runAction<Tag>('tag.create', data, signal)
    if (!res.data) throw new Error('Failed to create tag')
    return res.data
  }

  async updateTag(id: number, data: UpdateTagData, signal?: AbortSignal): Promise<Tag> {
    const res = await this.engine.runAction<Tag>('tag.update', { id, ...data }, signal)
    if (!res.data) throw new Error('Failed to update tag')
    return res.data
  }

  async deactivateTag(id: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('tag.deactivate', { id }, signal)
  }
}
