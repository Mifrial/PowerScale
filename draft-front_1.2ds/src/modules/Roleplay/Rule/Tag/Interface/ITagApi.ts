import type { Tag } from './types'

export interface CreateTagData {
  code: string
  name: string
  description?: string
}

export interface UpdateTagData {
  code?: string
  name?: string
  description?: string
  active?: boolean
}

export interface ITagApi {
  getTags(signal?: AbortSignal): Promise<Tag[]>
  getTag(id: number, signal?: AbortSignal): Promise<Tag>
  createTag(data: CreateTagData, signal?: AbortSignal): Promise<Tag>
  updateTag(id: number, data: UpdateTagData, signal?: AbortSignal): Promise<Tag>
  deactivateTag(id: number, signal?: AbortSignal): Promise<void>
}
