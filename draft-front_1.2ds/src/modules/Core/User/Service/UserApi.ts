import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';
import type { IUserApi } from '@/modules/Core/User/Interface/IUserApi';
import type { CreateUserData } from '@/modules/Core/User/Dto/CreateUserData';
import type { UpdateUserData } from '@/modules/Core/User/Dto/UpdateUserData';
import type { User } from '@/modules/Core/User/Dto/User';
import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';
import type { AvatarState } from '@/modules/Core/User/Dto/AvatarState';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { FindPageResult } from '@/modules/Core/User/Dto/FindPageResult';
import { UserApiError } from '@/modules/Core/User/Dto/UserApiError';

export class UserApi implements IUserApi {
  constructor(private readonly engine: Engine) {}

  async findPage(query: FindPageQuery, signal?: AbortSignal): Promise<FindPageResult<User>> {
    const res = await this.engine.runAction<FindPageResult<User>>('user.findPage', query, signal);
    if (!res.data) {
      return { items: [], total: 0 };
    }

    return res.data;
  }

  async getUser(id: number, signal?: AbortSignal): Promise<User> {
    const res = await this.engine.runAction<User>('user.get', { id }, signal);
    if (!res.data) throw new Error('User not found');

    return res.data;
  }

  async getUsersByIds(ids: number[], signal?: AbortSignal): Promise<User[]> {
    const res = await this.engine.runAction<User[]>('user.getByIds', { ids }, signal);

    return res.data ?? [];
  }

  async createUser(data: CreateUserData, signal?: AbortSignal): Promise<User> {
    const res = await this.engine.runAction<User>('user.create', data, signal);
    if (!res.data) throw new Error('Failed to create user');

    return res.data;
  }

  async updateUser(id: number, data: UpdateUserData, signal?: AbortSignal): Promise<User> {
    const res = await this.engine.runAction<User>('user.update', { id, ...data }, signal);
    if (!res.data) throw new Error('Failed to update user');

    return res.data;
  }

  async deactivateUser(id: number, reason?: string, deactivatedUntil?: string, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('user.deactivate', { id, reason, deactivatedUntil }, signal);
  }

  async setAvatar(filePublicId: string | null, crop: AvatarCrop | null, signal?: AbortSignal): Promise<User> {
    const res = await this.engine.runAction<User>(
      'user.setAvatar',
      {
        filePublicId,
        cropX: crop?.x ?? null,
        cropY: crop?.y ?? null,
        cropSize: crop?.size ?? null,
      },
      signal,
    );

    return this.requireData(res, 'Failed to set avatar');
  }

  async getAvatar(signal?: AbortSignal): Promise<AvatarState> {
    const res = await this.engine.runAction<AvatarState>('user.getAvatar', {}, signal);

    return this.requireData(res, 'Failed to load avatar');
  }

  private requireData<T>(response: ActionResponse<T>, fallbackMessage: string): T {
    if (response.success && response.data) return response.data;
    const error = response.error;

    throw new UserApiError(error?.code ?? 'INTERNAL', error?.message ?? fallbackMessage, error?.details);
  }
}
