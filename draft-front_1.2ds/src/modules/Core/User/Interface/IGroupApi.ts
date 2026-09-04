import type { Group } from '@/modules/Core/User/Dto/Group';
import type { GroupMember } from '@/modules/Core/User/Dto/GroupMember';
import type { CreateGroupData } from '@/modules/Core/User/Dto/CreateGroupData';
import type { UpdateGroupData } from '@/modules/Core/User/Dto/UpdateGroupData';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { FindPageResult } from '@/modules/Core/User/Dto/FindPageResult';

export interface IGroupApi {
  findPage(query: FindPageQuery, signal?: AbortSignal): Promise<FindPageResult<Group>>;
  getGroup(id: number, signal?: AbortSignal): Promise<Group>;
  getGroupMembers(
    groupId: number,
    query: { limit: number; offset: number },
    signal?: AbortSignal,
  ): Promise<FindPageResult<GroupMember>>;
  createGroup(data: CreateGroupData, signal?: AbortSignal): Promise<Group>;
  updateGroup(id: number, data: UpdateGroupData, signal?: AbortSignal): Promise<Group>;
  deactivateGroup(id: number, signal?: AbortSignal): Promise<void>;
}
