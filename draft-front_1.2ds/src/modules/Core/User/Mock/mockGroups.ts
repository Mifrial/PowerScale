import type { Group } from '@/modules/Core/User/Dto/Group';
import type { GroupMember } from '@/modules/Core/User/Dto/GroupMember';
import type { CreateGroupData } from '@/modules/Core/User/Dto/CreateGroupData';
import type { UpdateGroupData } from '@/modules/Core/User/Dto/UpdateGroupData';
import { GROUP_PERMISSIONS } from '@/modules/Core/User/Mock/GROUP_PERMISSIONS';
import { groupPermissions } from '@/modules/Core/User/Mock/groupPermissions';
import { abortableDelay } from '@/modules/Core/Engine/Mock/abortableDelay';
import { mockGroupMembers } from '@/modules/Core/User/Mock/mockGroupMembers';

let nextId = 4;

const groups: Group[] = [
  {
    id: 1,
    name: 'Администраторы',
    active: true,
    memberCount: 2,
    permissions: [],
    createdAt: '2026-01-01T00:00:00Z',
  },
  {
    id: 2,
    name: 'Игрок',
    active: true,
    memberCount: 8,
    permissions: [...GROUP_PERMISSIONS['Игрок']],
    createdAt: '2026-01-01T00:00:00Z',
  },
  {
    id: 3,
    name: 'Ведущий',
    active: true,
    memberCount: 3,
    permissions: [...GROUP_PERMISSIONS['Ведущий']],
    createdAt: '2026-02-15T00:00:00Z',
  },
];

/** Права «Администраторы» всегда = полный реестр (лениво, из реестра прав). */
function resolveGroupPermissions(g: Group): string[] {
  return g.name === 'Администраторы' ? groupPermissions(g.name) : [...g.permissions];
}

export async function fetchGroups(signal?: AbortSignal): Promise<Group[]> {
  await abortableDelay(300, signal);

  return groups.map((g) => ({ ...g, permissions: resolveGroupPermissions(g) }));
}

export async function fetchGroup(id: number, signal?: AbortSignal): Promise<Group> {
  await abortableDelay(300, signal);
  const g = groups.find((g) => g.id === id);
  if (!g) throw new Error(`Group ${id} not found`);

  return { ...g, permissions: resolveGroupPermissions(g) };
}

export async function getGroupMembers(_groupId: number, signal?: AbortSignal): Promise<GroupMember[]> {
  await abortableDelay(300, signal);

  return mockGroupMembers.map((m) => ({ ...m }));
}

export async function createGroup(data: CreateGroupData, signal?: AbortSignal): Promise<Group> {
  await abortableDelay(300, signal);
  const group: Group = {
    id: nextId++,
    name: data.name,
    active: true,
    memberCount: 0,
    permissions: [...data.permissions],
    createdAt: new Date().toISOString(),
  };
  groups.push(group);

  return { ...group };
}

export async function updateGroup(id: number, data: UpdateGroupData, signal?: AbortSignal): Promise<Group> {
  await abortableDelay(300, signal);
  const g = groups.find((g) => g.id === id);
  if (!g) throw new Error(`Group ${id} not found`);
  if (data.name !== undefined) g.name = data.name;
  if (data.permissions !== undefined) g.permissions = [...data.permissions];
  if (data.active !== undefined) g.active = data.active;

  return { ...g, permissions: resolveGroupPermissions(g) };
}

export async function deactivateGroup(id: number, signal?: AbortSignal): Promise<void> {
  await abortableDelay(300, signal);
  const g = groups.find((g) => g.id === id);
  if (g) g.active = false;
}
