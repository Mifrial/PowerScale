import type { Group } from './types'

export interface CreateGroupData {
  name: string
  permissions: string[]
}

export interface UpdateGroupData {
  name?: string
  permissions?: string[]
  active?: boolean
}

export interface IGroupApi {
  getGroups(signal?: AbortSignal): Promise<Group[]>
  getGroup(id: number, signal?: AbortSignal): Promise<Group>
  createGroup(data: CreateGroupData, signal?: AbortSignal): Promise<Group>
  updateGroup(id: number, data: UpdateGroupData, signal?: AbortSignal): Promise<Group>
  deactivateGroup(id: number, signal?: AbortSignal): Promise<void>
}
