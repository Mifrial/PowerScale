import type { User } from './types'

export interface CreateUserData {
  name: string
  login: string
  email: string
  password: string
  groups: string[]
}

export interface UpdateUserData {
  name?: string
  surname?: string
  nickname?: string
  email?: string
  groups?: string[]
  active?: boolean
}

export interface IUserApi {
  getUsers(signal?: AbortSignal): Promise<User[]>
  getUser(id: number, signal?: AbortSignal): Promise<User>
  getUsersByIds(ids: number[], signal?: AbortSignal): Promise<User[]>
  createUser(data: CreateUserData, signal?: AbortSignal): Promise<User>
  updateUser(id: number, data: UpdateUserData, signal?: AbortSignal): Promise<User>
  deactivateUser(id: number, reason?: string, deactivatedUntil?: string, signal?: AbortSignal): Promise<void>
}
