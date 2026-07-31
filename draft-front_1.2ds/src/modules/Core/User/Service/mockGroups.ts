import type { Group } from '../Interface/types'
import type { CreateGroupData, UpdateGroupData } from '../Interface/IGroupApi'

const delay = (ms = 300) => new Promise(r => setTimeout(r, ms))

let nextId = 4

const groups: Group[] = [
  {
    id: 1,
    name: 'Администраторы',
    active: true,
    memberCount: 2,
    permissions: [
      'user.view', 'user.view_sensitive', 'user.create', 'user.edit', 'user.deactivate',
      'user_group.view', 'user_group.create', 'user_group.edit', 'user_group.deactivate',
      'space.create', 'space.view_all', 'space.edit_all',
      'rule.view', 'rule.create', 'rule.edit', 'rule.delete',
      'character.create', 'character.view',
      'game.create', 'game.view_all', 'game.edit_all',
      'tag.view', 'tag.create', 'tag.edit', 'tag.delete',
      'notification_template.view', 'notification_template.create', 'notification_template.edit', 'notification_template.delete',
    ],
    createdAt: '2026-01-01T00:00:00Z',
  },
  {
    id: 2,
    name: 'Игроки',
    active: true,
    memberCount: 8,
    permissions: ['user.view', 'character.create'],
    createdAt: '2026-01-01T00:00:00Z',
  },
  {
    id: 3,
    name: 'Модераторы',
    active: true,
    memberCount: 3,
    permissions: ['user.view', 'character.create', 'character.view', 'space.view_all', 'rule.view'],
    createdAt: '2026-02-15T00:00:00Z',
  },
]

export async function fetchGroups(_signal?: AbortSignal): Promise<Group[]> {
  await delay()
  return groups.map(g => ({ ...g }))
}

export async function fetchGroup(id: number, _signal?: AbortSignal): Promise<Group> {
  await delay()
  const g = groups.find(g => g.id === id)
  if (!g) throw new Error(`Group ${id} not found`)
  return { ...g }
}

export async function createGroup(data: CreateGroupData, _signal?: AbortSignal): Promise<Group> {
  await delay()
  const group: Group = {
    id: nextId++,
    name: data.name,
    active: true,
    memberCount: 0,
    permissions: [...data.permissions],
    createdAt: new Date().toISOString(),
  }
  groups.push(group)
  return { ...group }
}

export async function updateGroup(id: number, data: UpdateGroupData, _signal?: AbortSignal): Promise<Group> {
  await delay()
  const g = groups.find(g => g.id === id)
  if (!g) throw new Error(`Group ${id} not found`)
  if (data.name !== undefined) g.name = data.name
  if (data.permissions !== undefined) g.permissions = [...data.permissions]
  if (data.active !== undefined) g.active = data.active
  return { ...g }
}

export async function deactivateGroup(id: number, _signal?: AbortSignal): Promise<void> {
  await delay()
  const g = groups.find(g => g.id === id)
  if (g) g.active = false
}
