import type { User } from '@/modules/Core/User/Dto/User';
import type { CreateUserData } from '@/modules/Core/User/Dto/CreateUserData';
import type { FindPageQuery } from '@/modules/Core/User/Dto/FindPageQuery';
import type { FindPageResult } from '@/modules/Core/User/Dto/FindPageResult';
import { abortableDelay } from '@/modules/Core/Engine/Mock/abortableDelay';
import { resolvePermissions } from '@/modules/Core/User/Mock/resolvePermissions';

let nextId = 100;

function utc(year: number, month: number, day: number, hour = 0, minute = 0): number {
  return Math.floor(Date.UTC(year, month - 1, day, hour, minute) / 1000);
}

const catalog: Omit<User, 'permissions'>[] = [
  {
    id: 1,
    name: 'Иван',
    surname: 'Петров',
    nickname: 'IvanTheGreat',
    login: 'ivan_p',
    email: 'player@test.com',
    groups: [2],
    registered: utc(2026, 1, 1),
    active: true,
    lastLogin: utc(2026, 7, 27, 18, 30),
    bypass: false,
  },
  {
    id: 2,
    name: 'Администратор',
    login: 'admin',
    email: 'admin@test.com',
    groups: [1, 2],
    registered: utc(2025, 1, 1),
    active: true,
    lastLogin: utc(2026, 7, 28, 9, 0),
    bypass: true,
  },
  {
    id: 3,
    name: 'Анна',
    surname: 'Смирнова',
    nickname: 'Annet',
    login: 'anna_s',
    email: 'gm@test.com',
    groups: [3, 2],
    registered: utc(2026, 2, 15),
    active: true,
    lastLogin: utc(2026, 7, 27, 22, 15),
    bypass: false,
  },
  {
    id: 4,
    name: 'Пётр',
    surname: 'Козлов',
    login: 'petr_k',
    email: 'petr@test.com',
    groups: [2],
    registered: utc(2026, 3, 10),
    active: true,
    lastLogin: utc(2026, 7, 26, 14, 0),
    bypass: false,
  },
  {
    id: 5,
    name: 'Елена',
    surname: 'Морозова',
    nickname: 'LenaMagic',
    login: 'elena_m',
    email: 'elena@test.com',
    groups: [3, 2],
    registered: utc(2026, 4, 22),
    active: true,
    lastLogin: utc(2026, 7, 28, 7, 45),
    bypass: false,
  },
  {
    id: 6,
    name: 'Дмитрий',
    surname: 'Волков',
    login: 'dmitry_v',
    email: 'dmitry@test.com',
    groups: [2],
    registered: utc(2026, 5, 5),
    active: false,
    lastLogin: utc(2026, 6, 15, 11, 20),
    bypass: false,
  },
  {
    id: 7,
    name: 'Ольга',
    surname: 'Новикова',
    nickname: 'OlyaN',
    login: 'olga_n',
    email: 'olga@test.com',
    groups: [1, 3, 2],
    registered: utc(2025, 3, 1),
    active: true,
    lastLogin: utc(2026, 7, 28, 8, 30),
    bypass: true,
  },
  {
    id: 8,
    name: 'Сергей',
    surname: 'Лебедев',
    login: 'sergey_l',
    email: 'sergey@test.com',
    groups: [2],
    registered: utc(2026, 6, 18),
    active: true,
    lastLogin: utc(2026, 7, 25, 20, 10),
    bypass: false,
  },
  {
    id: 9,
    name: 'Мария',
    surname: 'Соколова',
    login: 'maria_s',
    email: 'maria@test.com',
    groups: [3, 2],
    registered: utc(2025, 7, 30),
    active: false,
    bypass: false,
  },
  {
    id: 10,
    name: 'Алексей',
    surname: 'Фёдоров',
    nickname: 'AlexF',
    login: 'alexey_f',
    email: 'alexey@test.com',
    groups: [2],
    registered: utc(2026, 8, 12),
    active: true,
    lastLogin: utc(2026, 7, 27, 16, 55),
    bypass: false,
  },
];

export const users: User[] = catalog.map((user) => ({
  ...user,
  permissions: resolvePermissions(user.groups),
}));

function toHttpUser(user: User): User {
  const { lastLogin: _lastLogin, ...rest } = user;

  return rest;
}

export async function mockFindPage(query: FindPageQuery, signal?: AbortSignal): Promise<FindPageResult<User>> {
  await abortableDelay(150, signal);
  const needle = query.q?.trim().toLowerCase() ?? '';
  const matched = users.filter((user) => {
    if (query.active !== undefined && user.active !== query.active) return false;
    if (!needle) return true;

    return [user.login, user.name, user.surname, user.nickname, user.email]
      .filter((part): part is string => !!part)
      .some((part) => part.toLowerCase().includes(needle));
  });
  const offset = query.offset;
  const items = matched.slice(offset, offset + query.limit).map(toHttpUser);

  return { items, total: matched.length };
}

export async function mockGetUser(id: number, signal?: AbortSignal): Promise<User> {
  await abortableDelay(150, signal);
  const u = users.find((x) => x.id === id);
  if (!u) throw new Error('User not found');

  return toHttpUser(u);
}

export async function mockGetUsersByIds(ids: number[], signal?: AbortSignal): Promise<User[]> {
  await abortableDelay(150, signal);
  const idSet = new Set(ids);

  return users.filter((u) => idSet.has(u.id)).map(toHttpUser);
}

export async function mockCreateUser(data: CreateUserData, signal?: AbortSignal): Promise<User> {
  await abortableDelay(150, signal);
  const id = nextId++;
  const user: User = {
    id,
    name: data.name,
    login: data.login,
    email: data.email ?? null,
    groups: data.groups,
    registered: Math.floor(Date.now() / 1000),
    active: true,
    bypass: false,
    permissions: resolvePermissions(data.groups),
    surname: data.surname,
    nickname: data.nickname,
  };
  users.push(user);

  return { ...user };
}

export async function mockUpdateUser(id: number, data: Record<string, unknown>, signal?: AbortSignal): Promise<User> {
  await abortableDelay(150, signal);
  const u = users.find((x) => x.id === id);
  if (!u) throw new Error('User not found');
  Object.assign(u, data);

  return toHttpUser(u);
}

export async function mockDeactivateUser(
  id: number,
  reason?: string,
  deactivatedUntil?: string,
  signal?: AbortSignal,
): Promise<void> {
  await abortableDelay(150, signal);
  const u = users.find((x) => x.id === id);
  if (!u) throw new Error('User not found');
  u.active = false;
  u.deactivateReason = reason ?? null;
  u.deactivatedUntil = deactivatedUntil ? Math.floor(Date.parse(`${deactivatedUntil}T00:00:00Z`) / 1000) : null;
}
