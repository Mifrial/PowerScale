import type { User } from '@/modules/Core/User/Dto/User';
import type { CreateUserData } from '@/modules/Core/User/Dto/CreateUserData';
import type { UpdateUserData } from '@/modules/Core/User/Dto/UpdateUserData';

export interface IUserApi {
  getUsers(signal?: AbortSignal): Promise<User[]>;
  getUser(id: number, signal?: AbortSignal): Promise<User>;
  getUsersByIds(ids: number[], signal?: AbortSignal): Promise<User[]>;
  createUser(data: CreateUserData, signal?: AbortSignal): Promise<User>;
  updateUser(id: number, data: UpdateUserData, signal?: AbortSignal): Promise<User>;
  deactivateUser(id: number, reason?: string, deactivatedUntil?: string, signal?: AbortSignal): Promise<void>;
}
