import type { User } from '@/modules/Core/User/Dto/User';
import type { PasswordPolicy } from '@/modules/Core/Auth/Dto/PasswordPolicy';
import type { CurrentSession } from '@/modules/Core/Auth/Dto/CurrentSession';
import { resolvePermissions } from '@/modules/Core/User/Mock/resolvePermissions';
import { DEFAULT_PASSWORD_POLICY } from '@/modules/Core/Auth/Constant/defaultPasswordPolicy';
import { abortableDelay } from '@/modules/Core/Engine/Mock/abortableDelay';

type MockUser = Omit<User, 'permissions'> & { password: string };

// Осознанное разделение: mockAuth содержит только тестовые учётные данные для авторизации.
// Полные данные пользователей хранятся в Core/User/Mock/mockUsers.ts.
// Пароли не должны храниться на клиенте — это только mock для тестирования.
function utc(year: number, month: number, day: number, hour = 0, minute = 0): number {
  return Math.floor(Date.UTC(year, month - 1, day, hour, minute) / 1000);
}

const mockUsers: MockUser[] = [
  {
    id: 1,
    name: 'Иван',
    surname: 'Петров',
    nickname: 'IvanTheGreat',
    login: 'ivan_p',
    email: 'player@test.com',
    password: 'test',
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
    password: 'test',
    groups: [1, 2],
    registered: utc(2025, 1, 1),
    active: true,
    lastLogin: utc(2026, 7, 28, 9, 0),
    bypass: true,
  },
  {
    id: 4,
    name: 'Без почты',
    login: 'no_mail',
    email: null,
    password: 'test',
    groups: [2],
    registered: utc(2026, 3, 1),
    active: true,
    lastLogin: utc(2026, 7, 20, 12, 0),
    bypass: false,
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
const GUEST_KEY = 'mock_session_guest';

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

function readStoredGuest(): boolean {
  try {
    return window.sessionStorage.getItem(GUEST_KEY) === '1';
  } catch {
    return false;
  }
}

function writeStoredGuest(guest: boolean): void {
  try {
    if (guest) window.sessionStorage.setItem(GUEST_KEY, '1');
    else window.sessionStorage.removeItem(GUEST_KEY);
  } catch {
    /* noop */
  }
}

let loggedInUserId: number | null = readStoredUserId();
let guestSession = readStoredGuest();

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

export async function mockGetPasswordPolicy(_userId?: number): Promise<PasswordPolicy> {
  await abortableDelay(200);

  return { ...DEFAULT_PASSWORD_POLICY };
}

export async function mockLogin(loginOrEmail: string, password: string, _remember = false): Promise<SafeUser> {
  await abortableDelay(200);
  const user = mockUsers.find((u) => (u.email === loginOrEmail || u.login === loginOrEmail) && u.password === password);
  if (!user) throw new Error('Неверный логин или пароль');
  loggedInUserId = user.id;
  guestSession = false;
  writeStoredUserId(user.id);
  writeStoredGuest(false);

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
    email: email.trim() === '' ? null : email,
    password,
    groups: [2],
    registered: Math.floor(Date.now() / 1000),
    active: true,
    bypass: false,
  };
  mockUsers.push(newUser);
  loggedInUserId = newUser.id;
  guestSession = false;
  writeStoredUserId(newUser.id);
  writeStoredGuest(false);

  return toSafeUser(newUser);
}

export async function mockStartPasswordReset(loginOrEmail: string): Promise<PasswordResetStartResult> {
  await abortableDelay(200);
  const user = mockUsers.find((u) => u.login === loginOrEmail || u.email === loginOrEmail);
  if (!user || !user.active) return { status: 'not_found' };
  if (user.email === null) return { status: 'no_email' };
  const resetToken = 'mock-reset-token';

  return { status: 'sent', login: user.login, resetToken };
}

export async function mockFinalPasswordReset(login: string, _token: string, newPassword: string): Promise<boolean> {
  await abortableDelay(200);
  const user = mockUsers.find((u) => u.login === login);
  if (!user) throw new Error('Неверный логин или код сброса');
  user.password = newPassword;

  return true;
}

export async function mockSetPassword(userId: number, newPassword: string, currentPassword?: string): Promise<boolean> {
  await abortableDelay(200);
  const user = mockUsers.find((u) => u.id === userId);
  if (!user) throw new Error('Пользователь не найден');
  if (loggedInUserId === userId && user.password !== currentPassword) {
    throw new Error('Неверный текущий пароль');
  }

  user.password = newPassword;

  return true;
}

export async function mockGuest(): Promise<void> {
  await abortableDelay(200);
  if (loggedInUserId !== null) throw new Error('Authentication failed');
  loggedInUserId = null;
  guestSession = true;
  writeStoredUserId(null);
  writeStoredGuest(true);
}

export async function mockLogout(): Promise<void> {
  await abortableDelay(200);
  loggedInUserId = null;
  guestSession = false;
  writeStoredUserId(null);
  writeStoredGuest(false);
}

export async function mockGetCurrentUser(): Promise<CurrentSession | null> {
  await abortableDelay(200);
  if (guestSession) return { kind: 'guest' };
  if (loggedInUserId === null) return null;
  const user = mockUsers.find((u) => u.id === loggedInUserId);
  if (!user) return null;

  return { kind: 'user', user: toSafeUser(user) };
}
