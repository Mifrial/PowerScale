import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Chat, ChatMessage, DiceRollSpec, SyncResponse } from '@/modules/Messages/Chat/Interface/types'
import { chatIcon, chatColor } from '@/modules/Messages/Chat/Config/chatType'
import { getChatApi } from '@/modules/Messages/Chat/init'
import { ChatSyncService } from '@/modules/Messages/Chat/Service/ChatSyncService'
import { usePermissions } from '@/modules/Messages/Chat/Composables/usePermissions'

const PAGE_SIZE = 20
const MAX_STORED = 100

export const useChatStore = defineStore('chat', () => {
  const chats = ref<Chat[]>([])
  const allMessages = ref<ChatMessage[]>([])
  const activeChatId = ref<number | null>(null)
  const loadingChats = ref(false)
  const loadingMessages = ref(false)
  const loadingOlder = ref(false)
  const sending = ref(false)
  const hasMoreOlder = ref(false)

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

  const renderedMessages = computed(() => allMessages.value)

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
    allMessages.value = []
    hasMoreOlder.value = true
    loadingMessages.value = true
    try {
      const [total, msgs] = await Promise.all([
        getChatApi().getTotalMessageCount(chatId),
        getChatApi().getMessages(chatId, PAGE_SIZE, 0),
      ])
      allMessages.value = msgs
      hasMoreOlder.value = allMessages.value.length < total
      await getChatApi().markChatRead(chatId)
      const chat = chats.value.find(c => c.id === chatId)
      if (chat) chat.unreadCount = 0
    } finally {
      loadingMessages.value = false
    }
  }

  async function loadOlderMessages() {
    if (!activeChatId.value || !hasMoreOlder.value || loadingOlder.value) return
    loadingOlder.value = true
    try {
      const older = await getChatApi().getMessages(
        activeChatId.value,
        PAGE_SIZE,
        allMessages.value.length,
      )
      if (older.length === 0) {
        hasMoreOlder.value = false
        return
      }
      allMessages.value = [...older, ...allMessages.value].slice(-MAX_STORED)
      hasMoreOlder.value = older.length >= PAGE_SIZE
    } finally {
      loadingOlder.value = false
    }
  }

  async function sendMessage(content: string, rolls: DiceRollSpec[]) {
    if (!activeChatId.value) return
    sending.value = true
    try {
      const msg = await getChatApi().sendMessage(activeChatId.value, content, rolls)
      allMessages.value.push(msg)
      const chat = chats.value.find(c => c.id === activeChatId.value)
      if (chat) {
        chat.lastMessage = content || (rolls.length ? `${rolls[0].label || 'Бросок'}` : '')
        chat.lastMessageAt = msg.createdAt
        chat.unreadCount = 0
      }
      getChatApi().markChatRead(activeChatId.value)
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
      if (cid === activeChatId.value) {
        for (const m of msgs) {
          if (!allMessages.value.find(ex => ex.id === m.id)) {
            allMessages.value.push(m)
          }
        }
        if (autoScroll.value && msgs.length > 0) {
          const chat = chats.value.find(c => c.id === cid)
          if (chat && chat.unreadCount > 0) {
            chat.unreadCount = 0
            getChatApi().markChatRead(cid)
          }
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
    chats, allMessages, renderedMessages,
    activeChatId, activeChat,
    loadingChats, loadingMessages, loadingOlder, sending, hasMoreOlder,
    fetchChats, openChat, loadOlderMessages, sendMessage,
    startSync, stopSync, autoScroll, setAutoScroll, lastSyncTimestamp,
    selectedTab, tabs, sortedChats, currentTabChats,
    tabUnread, chatIcon, chatColor,
  }
})
