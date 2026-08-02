import { describe, it, expect, beforeEach } from 'vitest'
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator'
import type { IAuthApi } from '@/modules/Core/Auth/Interface/IAuthApi'
import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi'
import type { INotificationApi } from '@/modules/Messages/Notifications/Interface/INotificationApi'
import type { IUserApi } from '@/modules/Core/User/Interface/IUserApi'

const baseUser = { id: 1, name: 'Test', login: 'test', email: 'test@test.com', role: 'player', groups: ['Игрок'], registered: '', active: true }

const createMockAuthApi = (): IAuthApi => ({
  login: async () => ({ ...baseUser }),
  register: async () => ({ ...baseUser, id: 2, name: 'New', login: 'new' }),
  logout: async () => {},
  getCurrentUser: async () => null,
  findUser: async () => null,
  resetPassword: async () => true,
  getPasswordPolicy: async () => ({ minLength: 4, requireMixedCase: false, requireDigit: false, requireSpecialChar: false }),
})

const createMockChatApi = (): IChatApi => ({
  getChats: async () => [],
  getMessages: async () => [],
  getTotalMessageCount: async () => 0,
  sendMessage: async () => ({ id: 1, chatId: 1, userId: 1, username: '', content: '', rolls: [], createdAt: '', updatedAt: '' }),
  markChatRead: async () => {},
  sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
})

const createMockNotifApi = (): INotificationApi => ({
  fetchPage: async () => ({ items: [], total: 0, unreadCount: 0 }),
  markAsRead: async () => {},
  markAllAsRead: async () => {},
})

const createMockUserApi = (): IUserApi => ({
  getUsers: async () => [{ ...baseUser }],
  getUser: async () => ({ ...baseUser }),
  getUsersByIds: async () => [{ ...baseUser }],
  createUser: async () => ({ ...baseUser, id: 99 }),
  updateUser: async () => ({ ...baseUser }),
  deactivateUser: async () => {},
})

beforeEach(() => {
  serviceLocator.reset()
})

describe('ServiceLocator', () => {
  it('throws on get before set', () => {
    expect(() => serviceLocator.get('test')).toThrow('Service "test" not registered')
  })

  it('set + get roundtrip', () => {
    const obj = { foo: 1 }
    serviceLocator.set('test', obj)
    expect(serviceLocator.get<typeof obj>('test')).toBe(obj)
  })

  it('reset clears all', () => {
    serviceLocator.set('a', 1)
    serviceLocator.set('b', 2)
    serviceLocator.reset()
    expect(() => serviceLocator.get('a')).toThrow()
    expect(() => serviceLocator.get('b')).toThrow()
  })

  it('override existing key', () => {
    serviceLocator.set('x', 1)
    serviceLocator.set('x', 2)
    expect(serviceLocator.get<number>('x')).toBe(2)
  })

  it('generic typing works', () => {
    interface Foo { bar: string }
    const impl: Foo = { bar: 'hello' }
    serviceLocator.set<Foo>('foo', impl)
    const got = serviceLocator.get<Foo>('foo')
    expect(got.bar).toBe('hello')
  })
})
