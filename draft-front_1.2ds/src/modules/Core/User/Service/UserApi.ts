import type { Engine } from '@/modules/Core/Engine/Service/Engine'
import type { IUserApi, CreateUserData, UpdateUserData } from '@/modules/Core/User/Interface/IUserApi'
import type { User } from '@/modules/Core/User/Dto/User'

export class UserApi implements IUserApi {
  constructor(private readonly engine: Engine) {}

  async getUsers(signal?: AbortSignal): Promise<User[]> {
    const res = await this.engine.runAction<User[]>('user.getList', undefined, signal)
    return res.data ?? []
  }

  async getUser(id: number, signal?: AbortSignal): Promise<User> {
    const res = await this.engine.runAction<User>('user.get', { id }, signal)
    if (!res.data) throw new Error('User not found')
    return res.data
  }

  async getUsersByIds(ids: number[], signal?: AbortSignal): Promise<User[]> {
    const res = await this.engine.runAction<User[]>('user.getByIds', { ids }, signal)
    return res.data ?? []
  }

  async createUser(data: CreateUserData, signal?: AbortSignal): Promise<User> {
    const res = await this.engine.runAction<User>('user.create', data, signal)
    if (!res.data) throw new Error('Failed to create user')
    return res.data
  }

  async updateUser(id: number, data: UpdateUserData, signal?: AbortSignal): Promise<User> {
    const res = await this.engine.runAction<User>('user.update', { id, ...data }, signal)
    if (!res.data) throw new Error('Failed to update user')
    return res.data
  }

  async deactivateUser(id: number, reason?: string, deactivatedUntil?: string, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('user.deactivate', { id, reason, deactivatedUntil }, signal)
  }
}
