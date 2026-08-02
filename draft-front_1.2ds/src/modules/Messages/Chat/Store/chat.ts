import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat'
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage'
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse'
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec'
import { chatIcon, chatColor } from '@/modules/Messages/Chat/Config/chatType'
import { getChatApi } from '@/modules/Messages/Chat/init'
import { ChatSyncService } from '@/modules/Messages/Chat/Service/ChatSyncService'
import { usePermissions } from '@/modules/Messages/Chat/Composables/usePermissions'

const PAGE_SIZE = 20
const MAX_STORED = 100

interface ChatState {
  messages: ChatMessage[]
  hasMore: boolean
  total: number
  loading: boolean
  loadingOlder: boolean
}

function createChatState(): ChatState {
  return reactive({
    messages: [],
    hasMore: true,
    total: 0,
    loading: false,
    loadingOlder: false,
  })
}

export const useChatStore = defineStore('chat', () => {
  const chats = ref<Chat[]>([])
  const chatStates = ref<Map<number, ChatState>>(new Map())
  const activeChatId = ref<number | null>(null)
  const loadingChats = ref(false)
  const sending = ref(false)

  const selectedTab = ref<string>('personal')

  const tabs = [
    { key: 'personal', label: 'Сообщения', icon: 'mdi-account' },
    { key: 'game', label: 'Игровые', icon: 'mdi-dice-d6' },
    { key: 'game_discussion', label: 'Обсуждения игр', icon: 'mdi-forum' },
    { key: 'character_discussion', label: 'Обсуждения персонажей', icon: 'mdi-account-details' },
  ]

  const sortedChats = computed(() =>
    [...chats.value].sort((a, b) => b.lastMessageAt.localeCompare(a.lastMessageAt)),
  )

  const currentTabChats = computed(() => {
    if (selectedTab.value === 'personal') {
      return sortedChats.value.filter(c => c.type === 'private' || c.type === 'group')
    }
    return sortedChats.value.filter(c => c.type === selectedTab.value)
  })

  function tabUnread(key: string): number {
    return chats.value
      .filter(c => key === 'personal' ? (c.type === 'private' || c.type === 'group') : c.type === key)
      .reduce((sum, c) => sum + c.unreadCount, 0)
  }

  const activeChat = computed(() =>
    chats.value.find(c => c.id === activeChatId.value) ?? null,
  )

  const activeState = computed(() => {
    const chatId = activeChatId.value
    if (!chatId) return null
    return chatStates.value.get(chatId) ?? null
  })

  const allMessages = computed(() => activeState.value?.messages ?? [])
  const hasMoreOlder = computed(() => activeState.value?.hasMore ?? false)
  const loadingMessages = computed(() => activeState.value?.loading ?? false)
  const loadingOlder = computed(() => activeState.value?.loadingOlder ?? false)
  const renderedMessages = computed(() => allMessages.value)

  const firstUnreadMessageId = computed<number | null>(() => {
    const state = activeState.value
    const chat = activeChat.value
    if (!state || state.messages.length === 0) return null
    if (chat == null || chat.lastReadMessageId == null) return state.messages[0].id
    const first = state.messages.find(m => m.id > chat.lastReadMessageId!)
    return first ? first.id : null
  })

  async function fetchChats() {
    loadingChats.value = true
    try {
      const all = await getChatApi().getChats()
      const ability = usePermissions()
      chats.value = ability.isGuest.value ? all.filter(c => c.type !== 'private') : all
    } finally {
      loadingChats.value = false
    }
  }

  async function openChat(chatId: number) {
    if (activeChatId.value === chatId) return
    activeChatId.value = chatId

    let state = chatStates.value.get(chatId)
    if (state) return

    state = createChatState()
    chatStates.value.set(chatId, state)
    state.loading = true

    try {
      const [total, msgs] = await Promise.all([
        getChatApi().getTotalMessageCount(chatId),
        getChatApi().getMessages(chatId, PAGE_SIZE, 0),
      ])
      state.messages = msgs
      state.total = total
      state.hasMore = msgs.length < total
      await getChatApi().markChatRead(chatId)
      const idx = chats.value.findIndex(c => c.id === chatId)
      if (idx !== -1) {
        chats.value[idx] = {
          ...chats.value[idx],
          unreadCount: 0,
          lastReadMessageId: msgs.length ? msgs[msgs.length - 1].id : null,
        }
      }
    } finally {
      state.loading = false
    }
  }

  async function loadOlderMessages() {
    const chatId = activeChatId.value
    if (!chatId) return
    const state = chatStates.value.get(chatId)
    if (!state || !state.hasMore || state.loadingOlder) return

    state.loadingOlder = true
    try {
      const older = await getChatApi().getMessages(
        chatId,
        PAGE_SIZE,
        state.messages.length,
      )
      if (older.length === 0) {
        state.hasMore = false
        return
      }
      state.messages = [...older, ...state.messages].slice(-MAX_STORED)
      state.hasMore = older.length >= PAGE_SIZE
    } finally {
      state.loadingOlder = false
    }
  }

  async function sendMessage(content: string, rolls: DiceRollSpec[]) {
    const chatId = activeChatId.value
    if (!chatId) return

    let state = chatStates.value.get(chatId)
    if (!state) {
      state = createChatState()
      chatStates.value.set(chatId, state)
    }

    sending.value = true
    try {
      const msg = await getChatApi().sendMessage(chatId, content, rolls)
      state.messages.push(msg)
      state.total++
      const chat = chats.value.find(c => c.id === chatId)
      if (chat) {
        chat.lastMessage = content || (rolls.length ? `${rolls[0].label || 'Бросок'}` : '')
        chat.lastMessageAt = msg.createdAt
        chat.unreadCount = 0
        chat.lastReadMessageId = msg.id
      }
      getChatApi().markChatRead(chatId)
    } finally {
      sending.value = false
    }
  }

  const autoScroll = ref(true)
  const lastSyncTimestamp = ref('')
  let syncService: ChatSyncService | null = null
  let syncRefCount = 0

  function setAutoScroll(val: boolean) {
    autoScroll.value = val
  }

  function applySyncResponse(data: SyncResponse) {
    lastSyncTimestamp.value = data.now
    for (const updated of data.chats) {
      const idx = chats.value.findIndex(c => c.id === updated.id)
      if (idx !== -1) {
        chats.value[idx] = { ...chats.value[idx], ...updated }
      }
    }
    for (const nc of data.newChats) {
      if (chats.value.find(c => c.id === nc.id)) continue
      const ability = usePermissions()
      if (ability.isGuest.value && nc.type === 'private') continue
      chats.value.push(nc)
    }
    for (const [chatIdStr, msgs] of Object.entries(data.messages)) {
      const cid = Number(chatIdStr)
      let state = chatStates.value.get(cid)
      if (!state) {
        state = createChatState()
        chatStates.value.set(cid, state)
      }
      for (const m of msgs) {
        if (!state.messages.find(ex => ex.id === m.id)) {
          state.messages.push(m)
        }
      }
      if (cid === activeChatId.value && autoScroll.value && msgs.length > 0) {
        const chat = chats.value.find(c => c.id === cid)
        if (chat && chat.unreadCount > 0) {
          chat.unreadCount = 0
          chat.lastReadMessageId = msgs.reduce((m, x) => Math.max(m, x.id), chat.lastReadMessageId ?? 0)
          getChatApi().markChatRead(cid)
        }
      }
    }
  }

  function startSync() {
    syncRefCount++
    if (syncService) return
    syncService = new ChatSyncService({
      onSync: (data) => applySyncResponse(data),
      getSyncApi: () => getChatApi(),
    })
    syncService.connect(lastSyncTimestamp.value)
  }

  function stopSync() {
    syncRefCount = Math.max(0, syncRefCount - 1)
    if (syncRefCount > 0) return
    if (syncService) {
      syncService.disconnect()
      syncService = null
    }
  }

  return {
    chats, allMessages, renderedMessages, firstUnreadMessageId,
    activeChatId, activeChat,
    loadingChats, loadingMessages, loadingOlder, sending, hasMoreOlder,
    fetchChats, openChat, loadOlderMessages, sendMessage,
    startSync, stopSync, autoScroll, setAutoScroll, lastSyncTimestamp,
    applySyncResponse,
    selectedTab, tabs, sortedChats, currentTabChats,
    tabUnread, chatIcon, chatColor,
  }
})
