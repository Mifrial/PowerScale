import type { User } from '@/modules/Core/User/Dto/User'

const delay = (ms = 150, signal?: AbortSignal) => new Promise<void>((resolve, reject) => {
  const timer = setTimeout(() => {
    if (signal?.aborted) {
      reject(new DOMException('Aborted', 'AbortError'))
    } else {
      resolve()
    }
  }, ms)
  
  signal?.addEventListener('abort', () => {
    clearTimeout(timer)
    reject(new DOMException('Aborted', 'AbortError'))
  }, { once: true })
})

let nextId = 100

export const users: User[] = [
  { id: 1, name: 'Иван', surname: 'Петров', nickname: 'IvanTheGreat', login: 'ivan_p', email: 'player@test.com', groups: ['Игрок'], registered: '01.01.2026', active: true, lastLogin: '27.07.2026 18:30' },
  { id: 2, name: 'Администратор', login: 'admin', email: 'admin@test.com', groups: ['Администраторы', 'Игрок'], registered: '01.01.2025', active: true, lastLogin: '28.07.2026 09:00', super_admin: true },
  { id: 3, name: 'Анна', surname: 'Смирнова', nickname: 'Annet', login: 'anna_s', email: 'gm@test.com', groups: ['Ведущий', 'Игрок'], registered: '15.02.2026', active: true, lastLogin: '27.07.2026 22:15' },
  { id: 4, name: 'Пётр', surname: 'Козлов', login: 'petr_k', email: 'petr@test.com', groups: ['Игрок'], registered: '10.03.2026', active: true, lastLogin: '26.07.2026 14:00' },
  { id: 5, name: 'Елена', surname: 'Морозова', nickname: 'LenaMagic', login: 'elena_m', email: 'elena@test.com', groups: ['Ведущий', 'Игрок'], registered: '22.04.2026', active: true, lastLogin: '28.07.2026 07:45' },
  { id: 6, name: 'Дмитрий', surname: 'Волков', login: 'dmitry_v', email: 'dmitry@test.com', groups: ['Игрок'], registered: '05.05.2026', active: false, lastLogin: '15.06.2026 11:20' },
  { id: 7, name: 'Ольга', surname: 'Новикова', nickname: 'OlyaN', login: 'olga_n', email: 'olga@test.com', groups: ['Администраторы', 'Ведущий', 'Игрок'], registered: '01.03.2025', active: true, lastLogin: '28.07.2026 08:30' },
  { id: 8, name: 'Сергей', surname: 'Лебедев', login: 'sergey_l', email: 'sergey@test.com', groups: ['Игрок'], registered: '18.06.2026', active: true, lastLogin: '25.07.2026 20:10' },
  { id: 9, name: 'Мария', surname: 'Соколова', login: 'maria_s', email: 'maria@test.com', groups: ['Ведущий', 'Игрок'], registered: '30.07.2025', active: false },
  { id: 10, name: 'Алексей', surname: 'Фёдоров', nickname: 'AlexF', login: 'alexey_f', email: 'alexey@test.com', groups: ['Игрок'], registered: '12.08.2026', active: true, lastLogin: '27.07.2026 16:55' },
]

export async function mockGetUsers(signal?: AbortSignal): Promise<User[]> {
  await delay(150, signal)
  return [...users.map(u => ({ ...u }))]
}

export async function mockGetUser(id: number, signal?: AbortSignal): Promise<User> {
  await delay(150, signal)
  const u = users.find(x => x.id === id)
  if (!u) throw new Error('User not found')
  return { ...u }
}

export async function mockGetUsersByIds(ids: number[], signal?: AbortSignal): Promise<User[]> {
  await delay(150, signal)
  const idSet = new Set(ids)
  return users.filter(u => idSet.has(u.id)).map(u => ({ ...u }))
}

export async function mockCreateUser(data: { name: string; login: string; email: string; password: string; groups: string[] }, signal?: AbortSignal): Promise<User> {
  await delay(150, signal)
  const id = nextId++
  const user: User = { id, ...data, registered: new Date().toISOString().slice(0, 10).replace(/-/g, '.'), active: true }
  users.push(user)
  return { ...user }
}

export async function mockUpdateUser(id: number, data: Record<string, unknown>, signal?: AbortSignal): Promise<User> {
  await delay(150, signal)
  const u = users.find(x => x.id === id)
  if (!u) throw new Error('User not found')
  Object.assign(u, data)
  return { ...u }
}

export async function mockDeactivateUser(id: number, reason?: string, deactivatedUntil?: string, signal?: AbortSignal): Promise<void> {
  await delay(150, signal)
  const u = users.find(x => x.id === id)
  if (!u) throw new Error('User not found')
  u.active = false
  u.deactivate_reason = reason
  u.deactivated_until = deactivatedUntil
}
