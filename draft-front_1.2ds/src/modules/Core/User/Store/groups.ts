import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Group } from '@/modules/Core/User/Dto/Group';
import type { GroupMember } from '@/modules/Core/User/Dto/GroupMember';
import type { CreateGroupData } from '@/modules/Core/User/Dto/CreateGroupData';
import type { UpdateGroupData } from '@/modules/Core/User/Dto/UpdateGroupData';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { FindPageResult } from '@/modules/Core/User/Dto/FindPageResult';
import { getGroupApi } from '@/modules/Core/User/init';

export const useGroupStore = defineStore('groups', () => {
  const groups = ref<Group[]>([]);
  const total = ref(0);
  const currentGroup = ref<Group | null>(null);
  const groupMembers = ref<GroupMember[]>([]);
  const groupMembersTotal = ref(0);
  const loading = ref(false);

  async function findPage(query: FindPageQuery, signal?: AbortSignal): Promise<FindPageResult<Group>> {
    loading.value = true;
    try {
      const page = await getGroupApi().findPage(query, signal);
      groups.value = page.items;
      total.value = page.total;

      return page;
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return { items: [], total: 0 };
      console.error('findPage failed', e);
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function ensureGroups(ids: number[]): Promise<void> {
    const missing = ids.filter((id) => !groups.value.some((group) => group.id === id));
    for (const groupId of missing) {
      try {
        const group = await getGroupApi().getGroup(groupId);
        if (!groups.value.some((entry) => entry.id === group.id)) groups.value.push(group);
      } catch {
        // Имя в чипе останется id, пока группа не загрузится.
      }
    }
  }

  async function fetchGroup(id: number, signal?: AbortSignal): Promise<Group> {
    const group = await getGroupApi().getGroup(id, signal);
    currentGroup.value = group;

    return group;
  }

  async function fetchGroupMembers(
    groupId: number,
    query: { limit: number; offset: number },
    signal?: AbortSignal,
  ): Promise<FindPageResult<GroupMember>> {
    const page = await getGroupApi().getGroupMembers(groupId, query, signal);
    groupMembers.value = page.items;
    groupMembersTotal.value = page.total;

    return page;
  }

  async function createGroup(data: CreateGroupData, signal?: AbortSignal): Promise<Group> {
    const group = await getGroupApi().createGroup(data, signal);
    groups.value.push(group);
    total.value += 1;

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

  function getGroupName(groupId: number): string {
    return groups.value.find((group) => group.id === groupId)?.name ?? String(groupId);
  }

  function clearCurrent() {
    currentGroup.value = null;
    groupMembers.value = [];
    groupMembersTotal.value = 0;
  }

  return {
    groups,
    total,
    currentGroup,
    groupMembers,
    groupMembersTotal,
    loading,
    findPage,
    ensureGroups,
    fetchGroup,
    fetchGroupMembers,
    createGroup,
    updateGroup,
    deactivateGroup,
    getGroupName,
    clearCurrent,
  };
});
