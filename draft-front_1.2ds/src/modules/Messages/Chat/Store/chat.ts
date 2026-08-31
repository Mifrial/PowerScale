import { defineStore } from 'pinia';
import { ref, reactive, computed } from 'vue';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';
import { getChatApi, getChatTabs } from '@/modules/Messages/Chat/init';
import { useCurrentUser } from '@/modules/Core/User/init';
import { ChatSyncService } from '@/modules/Messages/Chat/Service/ChatSyncService';
import { ChatReadAckService } from '@/modules/Messages/Chat/Service/ChatReadAckService';
import { PAGE_SIZE } from '@/modules/Messages/Chat/Constant/Chat/PAGE_SIZE';
import { MAX_STORED } from '@/modules/Messages/Chat/Constant/Chat/MAX_STORED';
import { messagePreview } from '@/modules/Messages/Chat/Utils/messagePreview';
import type { IChatTab } from '@/modules/Messages/Chat/Interface/IChatTab';
import type { ChatSyncHealth } from '@/modules/Messages/Chat/Dto/ChatSyncHealth';
import type { ChatState } from '@/modules/Messages/Chat/Dto/ChatState';

function createChatState(): ChatState {
  return reactive({
    messages: [],
    loadedCount: 0,
    hasMore: true,
    total: 0,
    initialized: false,
    loading: false,
    loadingOlder: false,
    olderError: '',
  });
}

function mergeSyncedChat(local: Chat, incoming: Chat): Chat {
  const localRead = local.lastReadMessageId ?? 0;
  const incomingRead = incoming.lastReadMessageId ?? 0;
  if (localRead >= incomingRead) {
    return {
      ...incoming,
      lastReadMessageId: local.lastReadMessageId,
      unreadCount: local.unreadCount,
    };
  }

  return incoming;
}

function mergeMessages(target: ChatMessage[], incoming: ChatMessage[]): ChatMessage[] {
  const byId = new Map<number, ChatMessage>();
  for (const m of target) byId.set(m.id, m);
  for (const m of incoming) byId.set(m.id, m);

  return [...byId.values()].sort((a, b) => {
    const d = a.createdAt.localeCompare(b.createdAt);

    return d !== 0 ? d : a.id - b.id;
  });
}

export const useChatStore = defineStore('chat', () => {
  const { userId: currentUserId, isGuest } = useCurrentUser();
  const chats = ref<Chat[]>([]);
  const chatStates = ref<Map<number, ChatState>>(new Map());
  const activeChatId = ref<number | null>(null);
  const loadingChats = ref(false);
  const sending = ref(false);
  const chatsError = ref('');
  const chatError = ref('');
  const actionError = ref('');
  const syncHealth = ref<ChatSyncHealth>({ status: 'ok', lastError: null });
  const readAckHealth = reactive<Record<number, ChatSyncHealth>>({});

  const selectedTab = ref<string>('personal');

  const sortedChats = computed(() => [...chats.value].sort((a, b) => b.lastMessageAt.localeCompare(a.lastMessageAt)));

  const tabs = computed<IChatTab[]>(() => getChatTabs());

  function tabOf(key: string): IChatTab | undefined {
    return tabs.value.find((tab) => tab.key === key);
  }

  function isOnTab(chat: Chat, tab: IChatTab | undefined): boolean {
    if (!tab?.types.includes(chat.type)) return false;
    if (!tab.onlyIfMember) return true;
    const userId = currentUserId.value;

    return userId !== null && (chat.members?.some((member) => member.userId === userId) ?? false);
  }

  const currentTabChats = computed(() => {
    const tab = tabOf(selectedTab.value);

    return sortedChats.value.filter((chat) => isOnTab(chat, tab));
  });

  function tabUnread(key: string): number {
    const tab = tabOf(key);

    return chats.value.filter((chat) => isOnTab(chat, tab)).reduce((sum, chat) => sum + chat.unreadCount, 0);
  }

  const activeChat = computed(() => chats.value.find((c) => c.id === activeChatId.value) ?? null);

  const activeState = computed(() => {
    const chatId = activeChatId.value;
    if (!chatId) return null;

    return chatStates.value.get(chatId) ?? null;
  });

  const allMessages = computed(() => activeState.value?.messages ?? []);
  const hasMoreOlder = computed(() => activeState.value?.hasMore ?? false);
  const loadingMessages = computed(() => activeState.value?.loading ?? false);
  const loadingOlder = computed(() => activeState.value?.loadingOlder ?? false);

  const firstUnreadMessageId = computed<number | null>(() => {
    const state = activeState.value;
    const chat = activeChat.value;
    if (!state || state.messages.length === 0) return null;
    if (chat == null || chat.lastReadMessageId == null) return state.messages[0].id;
    const lastReadMessageId = chat.lastReadMessageId;
    const first = state.messages.find((m) => m.id > lastReadMessageId);

    return first ? first.id : null;
  });

  async function fetchChats() {
    loadingChats.value = true;
    chatsError.value = '';
    try {
      const all = await getChatApi().getChats();
      chats.value = isGuest.value ? all.filter((c) => c.type !== 'private') : all;
    } catch (e) {
      chatsError.value = e instanceof Error ? e.message : 'Не удалось загрузить чаты';
    } finally {
      loadingChats.value = false;
    }
  }

  async function loadChat(chatId: number) {
    let state = chatStates.value.get(chatId);
    if (state?.initialized) {
      markRead(chatId);

      return;
    }

    if (!state) {
      state = createChatState();
      chatStates.value.set(chatId, state);
    }
    chatError.value = '';
    state.loading = true;

    try {
      const [total, msgs] = await Promise.all([
        getChatApi().getTotalMessageCount(chatId),
        getChatApi().getMessages(chatId, PAGE_SIZE, 0),
      ]);
      state.messages = mergeMessages(state.messages, msgs);
      state.loadedCount = Math.max(state.loadedCount, msgs.length);
      state.total = total;
      state.hasMore = state.loadedCount < total;
      state.olderError = '';
      state.initialized = true;
      markRead(chatId);
      const idx = chats.value.findIndex((c) => c.id === chatId);
      if (idx !== -1) {
        chats.value[idx] = {
          ...chats.value[idx],
          unreadCount: 0,
          lastReadMessageId: state.messages.length ? state.messages[state.messages.length - 1].id : null,
        };
      }
    } catch (e) {
      chatError.value = e instanceof Error ? e.message : 'Не удалось открыть чат';
    } finally {
      state.loading = false;
    }
  }

  async function openChat(chatId: number) {
    if (activeChatId.value === chatId) return;
    activeChatId.value = chatId;

    await loadChat(chatId);
  }

  function chatStateOf(chatId: number): ChatState | null {
    return chatStates.value.get(chatId) ?? null;
  }

  function messagesOf(chatId: number): ChatMessage[] {
    return chatStateOf(chatId)?.messages ?? [];
  }

  function firstUnreadOf(chatId: number): number | null {
    const state = chatStateOf(chatId);
    const chat = chats.value.find((c) => c.id === chatId);
    if (!state || state.messages.length === 0) return null;
    if (chat == null || chat.lastReadMessageId == null) return state.messages[0].id;
    const lastReadMessageId = chat.lastReadMessageId;
    const first = state.messages.find((m) => m.id > lastReadMessageId);

    return first ? first.id : null;
  }

  function hasMoreOlderOf(chatId: number): boolean {
    return chatStateOf(chatId)?.hasMore ?? false;
  }

  function loadingOlderOf(chatId: number): boolean {
    return chatStateOf(chatId)?.loadingOlder ?? false;
  }

  function olderErrorOf(chatId: number): string {
    return chatStateOf(chatId)?.olderError ?? '';
  }

  const olderError = computed(() => activeState.value?.olderError ?? '');

  async function loadOlder(chatId: number) {
    const state = chatStates.value.get(chatId);
    if (!state || !state.hasMore || state.loadingOlder) return;

    const oldestId = state.messages.length ? state.messages[0].id : null;
    if (oldestId === null) {
      state.hasMore = false;

      return;
    }

    state.loadingOlder = true;
    state.olderError = '';
    try {
      const older = await getChatApi().getMessagesBefore(chatId, oldestId, PAGE_SIZE);
      const before = state.messages.length;
      state.messages = mergeMessages(state.messages, older);
      const added = state.messages.length - before;
      state.loadedCount = state.messages.length;
      // Курсор-терминатор: если страница не принесла новых уникальных id — истории больше нет.
      state.hasMore = added > 0 && older.length >= PAGE_SIZE;
    } catch (caught) {
      state.olderError = caught instanceof Error ? caught.message : 'Не удалось загрузить историю';
    } finally {
      state.loadingOlder = false;
    }
  }

  async function loadOlderMessages() {
    const chatId = activeChatId.value;
    if (!chatId) return;

    await loadOlder(chatId);
  }

  async function sendMessage(
    content: string,
    attachments: ChatAttachment[],
    targetChatId?: number,
    speaker?: ChatSpeaker,
    visibility?: ChatMessageVisibility,
    thread?: ChatThreadRef,
  ): Promise<boolean> {
    // Встроенное обсуждение шлёт в свой чат явно (targetChatId), не трогая глобальный активный.
    const chatId = targetChatId ?? activeChatId.value;
    if (!chatId) return false;

    let state = chatStates.value.get(chatId);
    if (!state) {
      state = createChatState();
      chatStates.value.set(chatId, state);
    }

    sending.value = true;
    actionError.value = '';
    try {
      const msg = await getChatApi().sendMessage(chatId, content, attachments, speaker, visibility, thread);
      state.messages.push(msg);
      state.total++;
      state.initialized = true;
      const chat = chats.value.find((c) => c.id === chatId);
      if (chat) {
        // Авто-вступление: написавший в чат персонажа становится его участником
        // (закрепляет чат во вкладке «Обсуждения персонажей»).
        if (currentUserId.value !== null && !(chat.members?.some((m) => m.userId === currentUserId.value) ?? false)) {
          chat.members = [
            ...(chat.members ?? []),
            { userId: currentUserId.value, status: 'member', role: 'member', joinedAt: new Date().toISOString() },
          ];
        }
        chat.lastMessage = messagePreview(content, attachments);
        chat.lastMessageAt = msg.createdAt;
        chat.unreadCount = 0;
        chat.lastReadMessageId = msg.id;
      }
      markRead(chatId);

      return true;
    } catch (e) {
      actionError.value = e instanceof Error ? e.message : 'Не удалось отправить сообщение';

      return false;
    } finally {
      sending.value = false;
    }
  }

  // Смена видимости уже отправленного сообщения (только отправитель): мок обновляет
  // видимость, локально обновляем сообщение (отправителю своё всегда видно — рефетч не нужен).
  async function updateMessageVisibility(
    chatId: number,
    messageId: number,
    visibility?: ChatMessageVisibility,
  ): Promise<boolean> {
    try {
      const updated = await getChatApi().updateMessageVisibility(chatId, messageId, visibility);
      const state = chatStates.value.get(chatId);
      if (state) {
        const idx = state.messages.findIndex((message) => message.id === messageId);
        if (idx !== -1) state.messages[idx] = updated;
      }

      return true;
    } catch (e) {
      actionError.value = e instanceof Error ? e.message : 'Не удалось изменить видимость сообщения';

      return false;
    }
  }

  // Системное уведомление (напр. «Ходит Имя» из шкалы инициативы): создаётся автоматически,
  // рендерится разделителем; `kind` — «default»/«highlighted» (акцентное, цветом primary).
  // Обновляет локальное состояние как обычное сообщение.
  async function postSystemMessage(
    content: string,
    targetChatId: number,
    kind: ChatMessage['kind'] = 'default',
    thread?: ChatThreadRef,
  ): Promise<boolean> {
    let state = chatStates.value.get(targetChatId);
    if (!state) {
      state = createChatState();
      chatStates.value.set(targetChatId, state);
    }

    try {
      const msg = await getChatApi().sendSystemMessage(targetChatId, content, kind, thread);
      state.messages.push(msg);
      state.total++;
      state.initialized = true;
      const chat = chats.value.find((c) => c.id === targetChatId);
      if (chat) {
        chat.lastMessage = content;
        chat.lastMessageAt = msg.createdAt;
        chat.unreadCount = 0;
        chat.lastReadMessageId = msg.id;
      }
      markRead(targetChatId);

      return true;
    } catch (e) {
      actionError.value = e instanceof Error ? e.message : 'Не удалось отправить системное сообщение';

      return false;
    }
  }

  const autoScroll = ref(true);
  const lastSyncTimestamp = ref('');
  // Инстанс создаётся здесь, а не в Service/Instance (правило 27): он store-bound
  // (onSync замыкается на state стора) и per-instance, а не app-синглтон.
  let syncService: ChatSyncService | null = null;
  let syncRefCount = 0;
  const readAckService = new ChatReadAckService({
    markChatRead: (chatId) => getChatApi().markChatRead(chatId),
    onStatus: (chatId, health) => {
      readAckHealth[chatId] = health;
    },
  });

  function setAutoScroll(val: boolean) {
    autoScroll.value = val;
  }

  function applySyncResponse(data: SyncResponse) {
    lastSyncTimestamp.value = data.now;
    for (const updated of data.chats) {
      const idx = chats.value.findIndex((c) => c.id === updated.id);
      if (idx !== -1) {
        chats.value[idx] = mergeSyncedChat(chats.value[idx], updated);
      }
    }
    for (const nc of data.newChats) {
      if (chats.value.find((c) => c.id === nc.id)) continue;
      if (isGuest.value && nc.type === 'private') continue;
      chats.value.push(nc);
    }
    for (const [chatIdStr, msgs] of Object.entries(data.messages)) {
      const cid = Number(chatIdStr);
      let state = chatStates.value.get(cid);
      if (!state) {
        state = createChatState();
        chatStates.value.set(cid, state);
      }
      state.messages = mergeMessages(state.messages, msgs);
      if (cid === activeChatId.value) {
        state.loadedCount = state.messages.length;
      }
      if (cid !== activeChatId.value) {
        state.messages = state.messages.slice(-MAX_STORED);
      }
      if (cid === activeChatId.value && autoScroll.value && msgs.length > 0) {
        const chat = chats.value.find((c) => c.id === cid);
        if (chat) {
          chat.unreadCount = 0;
          chat.lastReadMessageId = msgs.reduce((m, x) => Math.max(m, x.id), chat.lastReadMessageId ?? 0);
          markRead(cid);
        }
      }
    }
  }

  function markRead(chatId: number) {
    readAckService.request(chatId);
  }

  function startSync() {
    syncRefCount++;
    if (syncService) return;
    syncService = new ChatSyncService({
      onSync: (data) => applySyncResponse(data),
      onStatus: (health) => {
        syncHealth.value = health;
      },
      getSyncApi: () => getChatApi(),
    });
    syncService.connect(lastSyncTimestamp.value);
  }

  function stopSync() {
    syncRefCount = Math.max(0, syncRefCount - 1);
    if (syncRefCount > 0) return;
    if (syncService) {
      syncService.disconnect();
      syncService = null;
      syncHealth.value = { status: 'ok', lastError: null };
    }
    readAckService.disconnect();
    for (const key of Object.keys(readAckHealth)) {
      delete readAckHealth[Number(key)];
    }
  }

  function retrySync(): void {
    syncService?.retryNow();
  }

  function retryReadAck(chatId: number): void {
    readAckService.retryNow(chatId);
  }

  return {
    chats,
    chatStates,
    allMessages,
    firstUnreadMessageId,
    activeChatId,
    activeChat,
    loadingChats,
    loadingMessages,
    loadingOlder,
    olderError,
    sending,
    hasMoreOlder,
    chatsError,
    chatError,
    actionError,
    syncHealth,
    readAckHealth,
    fetchChats,
    openChat,
    loadChat,
    loadOlderMessages,
    loadOlder,
    messagesOf,
    firstUnreadOf,
    hasMoreOlderOf,
    loadingOlderOf,
    olderErrorOf,
    sendMessage,
    updateMessageVisibility,
    postSystemMessage,
    startSync,
    stopSync,
    retrySync,
    retryReadAck,
    autoScroll,
    setAutoScroll,
    lastSyncTimestamp,
    applySyncResponse,
    selectedTab,
    tabs,
    sortedChats,
    currentTabChats,
    tabUnread,
  };
});
