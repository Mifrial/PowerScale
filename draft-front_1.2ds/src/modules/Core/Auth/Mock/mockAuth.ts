import type { User } from '@/modules/Core/User/Dto/User';
import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';
import { resolvePermissions } from '@/modules/Core/User/Mock/resolvePermissions';
import { DEFAULT_PASSWORD_POLICY } from '@/modules/Core/Auth/Constant/defaultPasswordPolicy';
import { abortableDelay } from '@/modules/Core/Engine/Mock/abortableDelay';

type MockUser = Omit<User, 'permissions'> & { password: string };

// Осознанное разделение: mockAuth содержит только тестовые учётные данные для авторизации.
// Полные данные пользователей хранятся в Core/User/Mock/mockUsers.ts.
// Пароли не должны храниться на клиенте — это только mock для тестирования.
const mockUsers: MockUser[] = [
  {
    id: 1,
    name: 'Иван',
    surname: 'Петров',
    nickname: 'IvanTheGreat',
    login: 'ivan_p',
    email: 'player@test.com',
    password: 'test',
    groups: ['Игрок'],
    registered: '01.01.2026',
    active: true,
    lastLogin: '27.07.2026 18:30',
  },
  {
    id: 2,
    name: 'Администратор',
    login: 'admin',
    email: 'admin@test.com',
    password: 'test',
    groups: ['Администраторы', 'Игрок'],
    registered: '01.01.2025',
    active: true,
    lastLogin: '28.07.2026 09:00',
    super_admin: true,
  },
  {
    id: 3,
    name: 'Анна',
    surname: 'Смирнова',
    nickname: 'Annet',
    login: 'anna_s',
    email: 'gm@test.com',
    password: 'test',
    groups: ['Ведущий', 'Игрок'],
    registered: '15.02.2026',
    active: true,
    lastLogin: '27.07.2026 22:15',
  },
];

type SafeUser = Omit<MockUser, 'password'> & { permissions: string[] };

// Возвращает пользователя без пароля + permissions, мёрженные из групп (как отдал бы реальный бэк).
function toSafeUser(user: MockUser): SafeUser {
  const { password: _, ...safeUser } = user;

  return { ...safeUser, permissions: resolvePermissions(user.groups) };
}

// Мок «серверной сессии»: id вошедшего пользователя живёт в sessionStorage,
// чтобы сессия переживала обновление страницы, как реальная cookie-сессия.
const SESSION_KEY = 'mock_session_user_id';

function readStoredUserId(): number | null {
  try {
    const raw = window.sessionStorage.getItem(SESSION_KEY);
    if (raw === null) return null;
    const id = Number(raw);

    return Number.isInteger(id) && id > 0 ? id : null;
  } catch {
    return null;
  }
}

function writeStoredUserId(id: number | null): void {
  try {
    if (id === null) window.sessionStorage.removeItem(SESSION_KEY);
    else window.sessionStorage.setItem(SESSION_KEY, String(id));
  } catch {
    /* noop */
  }
}

let loggedInUserId: number | null = readStoredUserId();

// Текущий аутентифицированный пользователь. Fixtures чатов используют sentinel «я» (SELF = -1),
// который в рантайме заменяется на реального пользователя. По умолчанию — Иван Петров (id 1),
// если сессия не создана (тесты без mockLogin).
export function getCurrentUserId(): number {
  return loggedInUserId ?? 1;
}

/** Краткая сводка текущего пользователя для моков (синхронно): id + отображаемое имя. */
export function getCurrentUserSummary(): { id: number; name: string } {
  const id = loggedInUserId ?? 1;
  const user = mockUsers.find((u) => u.id === id);

  return { id, name: user ? [user.name, user.surname].filter(Boolean).join(' ') || user.login : 'Иван Петров' };
}

export async function mockGetPasswordPolicy(): Promise<PasswordPolicy> {
  await abortableDelay(200);

  return { ...DEFAULT_PASSWORD_POLICY };
}

export async function mockLogin(loginOrEmail: string, password: string): Promise<SafeUser> {
  await abortableDelay(200);
  const user = mockUsers.find((u) => (u.email === loginOrEmail || u.login === loginOrEmail) && u.password === password);
  if (!user) throw new Error('Неверный логин или пароль');
  loggedInUserId = user.id;
  writeStoredUserId(user.id);

  return toSafeUser(user);
}

export async function mockRegister(login: string, email: string, password: string): Promise<SafeUser> {
  await abortableDelay(200);
  if (mockUsers.find((u) => u.login === login)) throw new Error('Логин уже занят');
  if (email && mockUsers.find((u) => u.email === email)) throw new Error('Email уже используется');
  const newUser: MockUser = {
    id: mockUsers.length + 1,
    name: login,
    login,
    email,
    password,
    groups: ['Игрок'],
    registered: new Date().toLocaleDateString('ru-RU'),
    active: true,
  };
  mockUsers.push(newUser);
  loggedInUserId = newUser.id;
  writeStoredUserId(newUser.id);

  return toSafeUser(newUser);
}

export async function mockFindUser(loginOrEmail: string): Promise<SafeUser | null> {
  await abortableDelay(200);
  const user = mockUsers.find((u) => u.login === loginOrEmail || u.email === loginOrEmail);
  if (!user) return null;

  return toSafeUser(user);
}

export async function mockResetPassword(login: string, _token: string, _newPassword: string): Promise<boolean> {
  await abortableDelay(200);
  const user = mockUsers.find((u) => u.login === login);
  if (!user) throw new Error('Пользователь не найден');

  return true;
}

export async function mockLogout(): Promise<void> {
  await abortableDelay(200);
  loggedInUserId = null;
  writeStoredUserId(null);
}

export async function mockGetCurrentUser(): Promise<SafeUser | null> {
  await abortableDelay(200);
  if (loggedInUserId === null) return null;
  const user = mockUsers.find((u) => u.id === loggedInUserId);
  if (!user) return null;

  return toSafeUser(user);
}
