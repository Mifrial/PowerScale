import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Group } from '@/modules/Core/User/Dto/Group';
import type { GroupMember } from '@/modules/Core/User/Dto/GroupMember';
import type { CreateGroupData } from '@/modules/Core/User/Dto/CreateGroupData';
import type { UpdateGroupData } from '@/modules/Core/User/Dto/UpdateGroupData';
import { getGroupApi } from '@/modules/Core/User/init';

export const useGroupStore = defineStore('groups', () => {
  const groups = ref<Group[]>([]);
  const currentGroup = ref<Group | null>(null);
  const groupMembers = ref<GroupMember[]>([]);
  const loading = ref(false);

  async function fetchGroups(signal?: AbortSignal) {
    loading.value = true;
    try {
      groups.value = await getGroupApi().getGroups(signal);
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      console.error('fetchGroups failed', e);
    } finally {
      loading.value = false;
    }
  }

  async function fetchGroup(id: number, signal?: AbortSignal): Promise<Group> {
    const group = await getGroupApi().getGroup(id, signal);
    currentGroup.value = group;

    return group;
  }

  async function fetchGroupMembers(groupId: number, signal?: AbortSignal): Promise<GroupMember[]> {
    const members = await getGroupApi().getGroupMembers(groupId, signal);
    groupMembers.value = members;

    return members;
  }

  async function createGroup(data: CreateGroupData, signal?: AbortSignal): Promise<Group> {
    const group = await getGroupApi().createGroup(data, signal);
    groups.value.push(group);

    return group;
  }

  async function updateGroup(id: number, data: UpdateGroupData, signal?: AbortSignal): Promise<Group> {
    const group = await getGroupApi().updateGroup(id, data, signal);
    const idx = groups.value.findIndex((g) => g.id === id);
    if (idx !== -1) groups.value[idx] = group;
    if (currentGroup.value?.id === id) currentGroup.value = group;

    return group;
  }

  async function deactivateGroup(id: number, signal?: AbortSignal): Promise<void> {
    await getGroupApi().deactivateGroup(id, signal);
    const group = groups.value.find((g) => g.id === id);
    if (group) group.active = false;
    if (currentGroup.value?.id === id) currentGroup.value.active = false;
  }

  function clearCurrent() {
    currentGroup.value = null;
    groupMembers.value = [];
  }

  return {
    groups,
    currentGroup,
    groupMembers,
    loading,
    fetchGroups,
    fetchGroup,
    fetchGroupMembers,
    createGroup,
    updateGroup,
    deactivateGroup,
    clearCurrent,
  };
});
