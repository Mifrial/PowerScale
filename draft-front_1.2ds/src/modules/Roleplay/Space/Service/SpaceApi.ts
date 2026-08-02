import type { Engine } from '@/modules/Core/Engine/Service/Engine'
import type { ISpaceApi } from '../Interface/ISpaceApi'
import type { Space } from '@/modules/Roleplay/Space/Dto/Space'
import type { SpaceCreateData } from '@/modules/Roleplay/Space/Dto/SpaceCreateData'
import type { SpaceUpdateData } from '@/modules/Roleplay/Space/Dto/SpaceUpdateData'
import type { SpaceRevisionMeta } from '@/modules/Roleplay/Space/Dto/SpaceRevisionMeta'
import type { SpaceRevision } from '@/modules/Roleplay/Space/Dto/SpaceRevision'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'

export class SpaceApi implements ISpaceApi {
  constructor(private engine: Engine) {}

  async getSpaces(signal?: AbortSignal): Promise<Space[]> {
    const res = await this.engine.runAction<Space[]>('space.getList', undefined, signal)
    return res.data ?? []
  }

  async getSpace(id: number, signal?: AbortSignal): Promise<Space> {
    const res = await this.engine.runAction<Space>('space.get', { id }, signal)
    if (!res.data) throw new Error('Space not found')
    return res.data
  }

  async getSpaceByCode(code: string, signal?: AbortSignal): Promise<Space> {
    const res = await this.engine.runAction<Space>('space.getByCode', { code }, signal)
    if (!res.data) throw new Error('Space not found')
    return res.data
  }

  async createSpace(data: SpaceCreateData, signal?: AbortSignal): Promise<Space> {
    const res = await this.engine.runAction<Space>('space.create', data, signal)
    if (!res.data) throw new Error('Failed to create space')
    return res.data
  }

  async updateSpace(id: number, data: SpaceUpdateData, signal?: AbortSignal): Promise<Space> {
    const res = await this.engine.runAction<Space>('space.update', { id, ...data }, signal)
    if (!res.data) throw new Error('Failed to update space')
    return res.data
  }

  async deactivateSpace(id: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('space.deactivate', { id }, signal)
  }

  async getRevisions(spaceId: number, signal?: AbortSignal): Promise<SpaceRevisionMeta[]> {
    const res = await this.engine.runAction<SpaceRevisionMeta[]>('space.getRevisions', { spaceId }, signal)
    return res.data ?? []
  }

  async getRevision(spaceId: number, revision: number, signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
    const res = await this.engine.runAction<SpaceRevision<Rule>>('space.getRevision', { spaceId, revision }, signal)
    if (!res.data) throw new Error('Revision not found')
    return res.data
  }

  async commitDraft(spaceId: number, rules: Rule[], signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
    const res = await this.engine.runAction<SpaceRevision<Rule>>('space.commitDraft', { spaceId, rules }, signal)
    if (!res.data) throw new Error('Failed to commit draft')
    return res.data
  }
}
