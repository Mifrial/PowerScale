import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IGroupApi } from '@/modules/Core/User/Interface/IGroupApi';
import type { CreateGroupData } from '@/modules/Core/User/Dto/CreateGroupData';
import type { UpdateGroupData } from '@/modules/Core/User/Dto/UpdateGroupData';
import type { Group } from '@/modules/Core/User/Dto/Group';
import type { GroupMember } from '@/modules/Core/User/Dto/GroupMember';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { FindPageResult } from '@/modules/Core/User/Dto/FindPageResult';

export class GroupApi implements IGroupApi {
  constructor(private readonly engine: Engine) {}

  async findPage(query: FindPageQuery, signal?: AbortSignal): Promise<FindPageResult<Group>> {
    const res = await this.engine.runAction<FindPageResult<Group>>('userGroup.findPage', query, signal);
    if (!res.data) {
      return { items: [], total: 0 };
    }

    return res.data;
  }

  async getGroup(id: number, signal?: AbortSignal): Promise<Group> {
    const res = await this.engine.runAction<Group>('userGroup.get', { id }, signal);
    if (!res.data) throw new Error('Group not found');

    return res.data;
  }

  async getGroupMembers(
    groupId: number,
    query: { limit: number; offset: number },
    signal?: AbortSignal,
  ): Promise<FindPageResult<GroupMember>> {
    const res = await this.engine.runAction<FindPageResult<GroupMember>>(
      'userGroup.getMembers',
      { groupId, ...query },
      signal,
    );
    if (!res.data) {
      return { items: [], total: 0 };
    }

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
