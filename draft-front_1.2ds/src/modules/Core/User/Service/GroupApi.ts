import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IGroupApi, CreateGroupData, UpdateGroupData } from '@/modules/Core/User/Interface/IGroupApi';
import type { Group } from '@/modules/Core/User/Dto/Group';

export class GroupApi implements IGroupApi {
  constructor(private readonly engine: Engine) {}

  async getGroups(signal?: AbortSignal): Promise<Group[]> {
    const res = await this.engine.runAction<Group[]>('userGroup.getList', undefined, signal);

    return res.data ?? [];
  }

  async getGroup(id: number, signal?: AbortSignal): Promise<Group> {
    const res = await this.engine.runAction<Group>('userGroup.get', { id }, signal);
    if (!res.data) throw new Error('Group not found');

    return res.data;
  }

  async createGroup(data: CreateGroupData, signal?: AbortSignal): Promise<Group> {
    const res = await this.engine.runAction<Group>('userGroup.create', data, signal);
    if (!res.data) throw new Error('Failed to create group');

    return res.data;
  }

  async updateGroup(id: number, data: UpdateGroupData, signal?: AbortSignal): Promise<Group> {
    const res = await this.engine.runAction<Group>('userGroup.update', { id, ...data }, signal);
    if (!res.data) throw new Error('Failed to update group');

    return res.data;
  }

  async deactivateGroup(id: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('userGroup.deactivate', { id }, signal);
  }
}
