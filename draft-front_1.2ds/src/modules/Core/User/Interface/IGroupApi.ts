import type { Group } from '@/modules/Core/User/Dto/Group';
import type { GroupMember } from '@/modules/Core/User/Dto/GroupMember';
import type { CreateGroupData } from '@/modules/Core/User/Dto/CreateGroupData';
import type { UpdateGroupData } from '@/modules/Core/User/Dto/UpdateGroupData';

export interface IGroupApi {
  getGroups(signal?: AbortSignal): Promise<Group[]>;
  getGroup(id: number, signal?: AbortSignal): Promise<Group>;
  getGroupMembers(groupId: number, signal?: AbortSignal): Promise<GroupMember[]>;
  createGroup(data: CreateGroupData, signal?: AbortSignal): Promise<Group>;
  updateGroup(id: number, data: UpdateGroupData, signal?: AbortSignal): Promise<Group>;
  deactivateGroup(id: number, signal?: AbortSignal): Promise<void>;
}
