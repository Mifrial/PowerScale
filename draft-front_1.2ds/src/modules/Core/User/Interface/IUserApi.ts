import type { User } from '@/modules/Core/User/Dto/User';
import type { CreateUserData } from '@/modules/Core/User/Dto/CreateUserData';
import type { UpdateUserData } from '@/modules/Core/User/Dto/UpdateUserData';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { FindPageResult } from '@/modules/Core/User/Dto/FindPageResult';

export interface IUserApi {
  findPage(query: FindPageQuery, signal?: AbortSignal): Promise<FindPageResult<User>>;
  getUser(id: number, signal?: AbortSignal): Promise<User>;
  getUsersByIds(ids: number[], signal?: AbortSignal): Promise<User[]>;
  createUser(data: CreateUserData, signal?: AbortSignal): Promise<User>;
  updateUser(id: number, data: UpdateUserData, signal?: AbortSignal): Promise<User>;
  deactivateUser(id: number, reason?: string, deactivatedUntil?: string, signal?: AbortSignal): Promise<void>;
}
