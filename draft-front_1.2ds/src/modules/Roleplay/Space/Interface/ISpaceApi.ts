import type { Space, SpaceCreateData, SpaceUpdateData, SpaceRevisionMeta, SpaceRevision } from './types'
import type { Rule } from '@/modules/Roleplay/Rule/Interface/types'

export interface ISpaceApi {
  getSpaces(signal?: AbortSignal): Promise<Space[]>
  getSpace(id: number, signal?: AbortSignal): Promise<Space>
  getSpaceByCode(code: string, signal?: AbortSignal): Promise<Space>
  createSpace(data: SpaceCreateData, signal?: AbortSignal): Promise<Space>
  updateSpace(id: number, data: SpaceUpdateData, signal?: AbortSignal): Promise<Space>
  deactivateSpace(id: number, signal?: AbortSignal): Promise<void>

  getRevisions(spaceId: number, signal?: AbortSignal): Promise<SpaceRevisionMeta[]>
  getRevision(spaceId: number, revision: number, signal?: AbortSignal): Promise<SpaceRevision<Rule>>
  commitDraft(spaceId: number, rules: Rule[], signal?: AbortSignal): Promise<SpaceRevision<Rule>>
}
