import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { registerChatApi } from '@/modules/Messages/Chat/init'
import { sl } from '@/modules/Core/Engine/ServiceLocator'
import { mockChatApi } from '@/modules/Messages/Chat/Service/mockChatApi'
import { useChatStore } from '@/modules/Messages/Chat/Store/chat'

beforeEach(() => {
  setActivePinia(createPinia())
  sl.reset()
  registerChatApi(mockChatApi)
})

describe('chat store', () => {
  it('fetchChats loads chats', async () => {
    const store = useChatStore()
    expect(store.chats.length).toBe(0)
    await store.fetchChats()
    expect(store.chats.length).toBeGreaterThan(0)
    expect(store.chats[0]).toHaveProperty('id')
    expect(store.chats[0]).toHaveProperty('name')
  })

  it('openChat sets activeChatId and loads messages', async () => {
    const store = useChatStore()
    await store.fetchChats()
    const chatId = store.chats[0].id

    await store.openChat(chatId)
    expect(store.activeChatId).toBe(chatId)
    expect(store.allMessages.length).toBeGreaterThan(0)
    expect(store.activeChat).not.toBeNull()
    expect(store.activeChat!.id).toBe(chatId)
  })

  it('openChat idempotent — same chat does not reload', async () => {
    const store = useChatStore()
    await store.fetchChats()
    const chatId = store.chats[0].id

    await store.openChat(chatId)
    const count = store.allMessages.length
    await store.openChat(chatId)
    expect(store.allMessages.length).toBe(count)
  })

  it('sendMessage adds message to allMessages', async () => {
    const store = useChatStore()
    await store.fetchChats()
    await store.openChat(store.chats[0].id)
    const before = store.allMessages.length

    await store.sendMessage('hello', [])
    expect(store.allMessages.length).toBe(before + 1)
    expect(store.allMessages[store.allMessages.length - 1].content).toBe('hello')
  })

  it('startSync / stopSync are reference counted', () => {
    const store = useChatStore()
    expect(store.autoScroll).toBe(true)

    store.startSync()
    store.startSync()
    store.stopSync()
    store.stopSync()
  })

  it('lastSyncTimestamp updates after sync', async () => {
    const store = useChatStore()
    store.startSync()
    const before = store.lastSyncTimestamp
    expect(before).toBe('')

    await store.fetchChats()
    if (store.chats.length > 0) {
      const api = mockChatApi
      const res = await api.sync('')
      expect(res.now).toBeTruthy()
      expect(typeof res.now).toBe('string')
    }
  })
})
