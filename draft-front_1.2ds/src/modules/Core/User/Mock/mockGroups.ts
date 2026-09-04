import type { Group } from '@/modules/Core/User/Dto/Group';
import type { GroupMember } from '@/modules/Core/User/Dto/GroupMember';
import type { CreateGroupData } from '@/modules/Core/User/Dto/CreateGroupData';
import type { UpdateGroupData } from '@/modules/Core/User/Dto/UpdateGroupData';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { FindPageResult } from '@/modules/Core/User/Dto/FindPageResult';
import { GROUP_PERMISSIONS } from '@/modules/Core/User/Mock/GROUP_PERMISSIONS';
import { groupPermissions } from '@/modules/Core/User/Mock/groupPermissions';
import { abortableDelay } from '@/modules/Core/Engine/Mock/abortableDelay';
import { mockGroupMembers } from '@/modules/Core/User/Mock/mockGroupMembers';

let nextId = 4;

function utcDay(year: number, month: number, day: number): number {
  return Math.floor(Date.UTC(year, month - 1, day) / 1000);
}

const groups: Group[] = [
  {
    id: 1,
    name: 'Администраторы',
    active: true,
    memberCount: 2,
    permissions: [],
    createdAt: utcDay(2026, 1, 1),
    bypass: true,
    assignOnRegister: false,
  },
  {
    id: 2,
    name: 'Игрок',
    active: true,
    memberCount: 8,
    permissions: [...GROUP_PERMISSIONS['Игрок']],
    createdAt: utcDay(2026, 1, 1),
    bypass: false,
    assignOnRegister: true,
  },
  {
    id: 3,
    name: 'Ведущий',
    active: true,
    memberCount: 3,
    permissions: [...GROUP_PERMISSIONS['Ведущий']],
    createdAt: utcDay(2026, 2, 15),
    bypass: false,
    assignOnRegister: false,
  },
];

export function permissionKeysOfGroupId(groupId: number): string[] {
  const group = groups.find((entry) => entry.id === groupId);
  if (!group) return [];

  return resolveGroupPermissions(group);
}

/** Права «Администраторы» всегда = полный реестр (лениво, из реестра прав). */
function resolveGroupPermissions(g: Group): string[] {
  return g.name === 'Администраторы' ? groupPermissions(g.name) : [...g.permissions];
}

export async function mockFindPage(query: FindPageQuery, signal?: AbortSignal): Promise<FindPageResult<Group>> {
  await abortableDelay(300, signal);
  const needle = query.q?.trim().toLowerCase() ?? '';
  const matched = groups.filter((group) => {
    if (query.active !== undefined && group.active !== query.active) return false;
    if (!needle) return true;

    return group.name.toLowerCase().includes(needle);
  });
  const offset = query.offset;
  const items = matched.slice(offset, offset + query.limit).map((group) => ({
    ...group,
    permissions: resolveGroupPermissions(group),
  }));

  return { items, total: matched.length };
}

export async function fetchGroup(id: number, signal?: AbortSignal): Promise<Group> {
  await abortableDelay(300, signal);
  const g = groups.find((g) => g.id === id);
  if (!g) throw new Error(`Group ${id} not found`);

  return { ...g, permissions: resolveGroupPermissions(g) };
}

export async function getGroupMembers(
  _groupId: number,
  query: { limit: number; offset: number },
  signal?: AbortSignal,
): Promise<FindPageResult<GroupMember>> {
  await abortableDelay(300, signal);
  const items = mockGroupMembers.map((m) => ({ ...m }));
  const offset = Math.max(0, query.offset);
  const sliced = items.slice(offset, offset + query.limit);

  return { items: sliced, total: items.length };
}

export async function createGroup(data: CreateGroupData, signal?: AbortSignal): Promise<Group> {
  await abortableDelay(300, signal);
  const group: Group = {
    id: nextId++,
    name: data.name,
    active: true,
    memberCount: 0,
    permissions: [...data.permissions],
    createdAt: Math.floor(Date.now() / 1000),
    bypass: false,
    assignOnRegister: false,
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
