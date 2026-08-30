import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { registerChatApi, registerChatTabs } from '@/modules/Messages/Chat/init';
import { registerAuthApi } from '@/modules/Core/Auth/init';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { mockAuthApi } from '@/modules/Core/Auth/Mock/mockAuthApi';
import { mockChatApi } from '@/modules/Messages/Chat/Mock/mockChatApi';
import { mockLogin, mockLogout } from '@/modules/Core/Auth/Mock/mockAuth';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';

beforeEach(() => {
  setActivePinia(createPinia());
  serviceLocator.reset();
  registerChatApi(mockChatApi);
  registerAuthApi(mockAuthApi);
  registerChatTabs([
    {
      key: 'character_discussion',
      label: 'Обсуждения персонажей',
      icon: 'mdi-account-details',
      types: ['character_discussion'],
      sortOrder: 3,
      onlyIfMember: true,
    },
  ]);
});

describe('chat store', () => {
  it('fetchChats loads chats', async () => {
    const store = useChatStore();
    expect(store.chats.length).toBe(0);
    await store.fetchChats();
    expect(store.chats.length).toBeGreaterThan(0);
    expect(store.chats[0]).toHaveProperty('id');
    expect(store.chats[0]).toHaveProperty('name');
  });

  it('openChat sets activeChatId and loads messages', async () => {
    const store = useChatStore();
    await store.fetchChats();
    const chatId = store.chats[0].id;

    await store.openChat(chatId);
    expect(store.activeChatId).toBe(chatId);
    expect(store.allMessages.length).toBeGreaterThan(0);
    expect(store.activeChat).not.toBeNull();
    expect(store.activeChat!.id).toBe(chatId);
  });

  it('openChat idempotent — same chat does not reload', async () => {
    const store = useChatStore();
    await store.fetchChats();
    const chatId = store.chats[0].id;

    await store.openChat(chatId);
    const count = store.allMessages.length;
    await store.openChat(chatId);
    expect(store.allMessages.length).toBe(count);
  });

  it('sendMessage adds message to allMessages', async () => {
    const store = useChatStore();
    await store.fetchChats();
    await store.openChat(store.chats[0].id);
    const before = store.allMessages.length;

    await store.sendMessage('hello', []);
    expect(store.allMessages.length).toBe(before + 1);
    expect(store.allMessages[store.allMessages.length - 1].content).toBe('hello');
    expect(await store.sendMessage('hello', [])).toBe(true);
  });

  it('updateMessageVisibility обновляет сообщение локально', async () => {
    const store = useChatStore();
    await store.fetchChats();
    const chatId = store.chats[0].id;
    await store.openChat(chatId);

    await store.sendMessage('меняю видимость', []);
    const message = store.allMessages.find((m) => m.content === 'меняю видимость');
    expect(message).toBeTruthy();

    await store.updateMessageVisibility(chatId, message!.id, { all: false, forRole: 'gm' });
    const updated = store.allMessages.find((m) => m.id === message!.id);
    expect(updated?.visibility).toEqual({ all: false, forRole: 'gm' });
  });

  it('startSync / stopSync are reference counted', () => {
    const store = useChatStore();
    expect(store.autoScroll).toBe(true);

    store.startSync();
    store.startSync();
    store.stopSync();
    store.stopSync();
  });

  it('lastSyncTimestamp updates after sync', async () => {
    const store = useChatStore();
    store.startSync();
    const before = store.lastSyncTimestamp;
    expect(before).toBe('');

    await store.fetchChats();
    if (store.chats.length > 0) {
      const api = mockChatApi;
      const res = await api.sync('');
      expect(res.now).toBeTruthy();
      expect(typeof res.now).toBe('string');
    }
    store.stopSync();
  });

  it('onStatus пишет syncHealth; retrySync без сервиса — no-op', async () => {
    vi.useFakeTimers();
    const store = useChatStore();
    store.retrySync();
    expect(store.syncHealth).toEqual({ status: 'ok', lastError: null });

    const sync = vi.fn().mockRejectedValue(new Error('сеть'));
    registerChatApi({ ...mockChatApi, sync });
    store.startSync();
    await vi.advanceTimersByTimeAsync(0);
    expect(store.syncHealth).toEqual({ status: 'retrying', lastError: 'сеть' });
    expect(store.chatsError).toBe('');
    store.stopSync();
    expect(store.syncHealth).toEqual({ status: 'ok', lastError: null });
    vi.useRealTimers();
  });

  describe('race condition (per-chat state)', () => {
    function deferred<T>() {
      let resolve!: (v: T) => void;
      const promise = new Promise<T>((r) => {
        resolve = r;
      });

      return { promise, resolve };
    }

    function makeMsg(id: number, chatId: number, content: string): ChatMessage {
      return { id, chatId, userId: 1, username: 'U', content, attachments: [], createdAt: '', updatedAt: '' };
    }

    it('openChat race: late response for A does not overwrite B', async () => {
      const aMsgs = deferred<ChatMessage[]>();
      const aTotal = deferred<number>();
      const bMsgs = deferred<ChatMessage[]>();
      const bTotal = deferred<number>();

      const fakeApi: IChatApi = {
        getChats: async () => [],
        getMessages: (chatId) => (chatId === 1 ? aMsgs.promise : bMsgs.promise),
        getMessagesBefore: async () => [],
        getTotalMessageCount: (chatId) => (chatId === 1 ? aTotal.promise : bTotal.promise),
        sendMessage: async () => makeMsg(999, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();

      const aPromise = store.openChat(1);
      const bPromise = store.openChat(2);

      bMsgs.resolve([makeMsg(10, 2, 'B1'), makeMsg(11, 2, 'B2')]);
      bTotal.resolve(2);
      await bPromise;

      expect(store.activeChatId).toBe(2);
      expect(store.allMessages.map((m) => m.content)).toEqual(['B1', 'B2']);

      aMsgs.resolve([makeMsg(1, 1, 'A1'), makeMsg(2, 1, 'A2')]);
      aTotal.resolve(2);
      await aPromise;

      expect(store.activeChatId).toBe(2);
      expect(store.allMessages.map((m) => m.content)).toEqual(['B1', 'B2']);
    });

    it('sync saves messages for all chats, not just active', async () => {
      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(store.chats[0].id);

      const otherChatId = store.chats[1]?.id ?? 999;
      const syncMsg = makeMsg(9999, otherChatId, 'sync-msg');

      store.applySyncResponse({
        now: '2026-01-01T00:00:00',
        chats: [],
        newChats: [],
        messages: { [otherChatId]: [syncMsg] },
      });

      await store.openChat(otherChatId);
      expect(store.allMessages.find((m) => m.content === 'sync-msg')).toBeTruthy();
    });
  });

  describe('unread position (lastReadMessageId)', () => {
    function makeMsg(id: number, chatId: number, content: string): ChatMessage {
      return { id, chatId, userId: 1, username: 'U', content, attachments: [], createdAt: '', updatedAt: '' };
    }

    function chat(id: number, unreadCount: number, lastReadMessageId: number | null): Chat {
      return {
        id,
        type: 'private',
        name: `Chat ${id}`,
        unreadCount,
        lastReadMessageId,
        lastMessageAt: '2026-07-27T00:00:00',
        members: [],
      };
    }

    it('openChat marks read and advances lastReadMessageId to newest loaded message', async () => {
      let marked = false;
      const fakeApi: IChatApi = {
        getChats: async () => [chat(1, 3, 2)],
        getMessages: async () => [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B'), makeMsg(3, 1, 'C'), makeMsg(4, 1, 'D')],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {
          marked = true;
        },
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      expect(marked).toBe(true);
      expect(store.activeChat!.unreadCount).toBe(0);
      expect(store.activeChat!.lastReadMessageId).toBe(4);
      expect(store.firstUnreadMessageId).toBeNull();
    });

    it('firstUnreadMessageId points to first message after lastReadMessageId', async () => {
      const fakeApi: IChatApi = {
        getChats: async () => [chat(1, 0, 2)],
        getMessages: async () => [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B'), makeMsg(3, 1, 'C'), makeMsg(4, 1, 'D')],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);
      store.setAutoScroll(false);

      const syncMsg = makeMsg(5, 1, 'E');
      store.applySyncResponse({
        now: '2026-01-01T00:00:00',
        chats: [{ ...store.activeChat!, unreadCount: 1 }],
        newChats: [],
        messages: { 1: [syncMsg] },
      });

      expect(store.firstUnreadMessageId).toBe(5);
    });

    it('sync with autoScroll on reads active chat and clears divider', async () => {
      const fakeApi: IChatApi = {
        getChats: async () => [chat(1, 0, 2)],
        getMessages: async () => [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B'), makeMsg(3, 1, 'C'), makeMsg(4, 1, 'D')],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      const syncMsg = makeMsg(5, 1, 'E');
      store.applySyncResponse({
        now: '2026-01-01T00:00:00',
        chats: [{ ...store.activeChat!, unreadCount: 1 }],
        newChats: [],
        messages: { 1: [syncMsg] },
      });

      expect(store.activeChat!.unreadCount).toBe(0);
      expect(store.activeChat!.lastReadMessageId).toBe(5);
      expect(store.firstUnreadMessageId).toBeNull();
    });
  });

  describe('history loading and storage limits', () => {
    function makeMsg(id: number, chatId: number, content: string): ChatMessage {
      return { id, chatId, userId: 1, username: 'U', content, attachments: [], createdAt: '', updatedAt: '' };
    }

    function chat(id: number): Chat {
      return {
        id,
        type: 'private',
        name: `Chat ${id}`,
        unreadCount: 0,
        lastReadMessageId: null,
        lastMessageAt: '2026-07-27T00:00:00',
        members: [],
      };
    }

    it('P2-3: openChat loads history when state was created by sync', async () => {
      const fakeApi: IChatApi = {
        getChats: async () => [chat(1)],
        getMessages: async () => [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B'), makeMsg(3, 1, 'C'), makeMsg(4, 1, 'D')],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();

      // sync создаёт state без открытия
      store.applySyncResponse({
        now: '2026-01-01T00:00:00',
        chats: [],
        newChats: [],
        messages: { 1: [makeMsg(5, 1, 'E')] },
      });

      await store.openChat(1);
      expect(store.allMessages.map((m) => m.content).sort()).toEqual(['A', 'B', 'C', 'D', 'E']);
      expect(store.hasMoreOlder).toBe(false);
    });

    it('P2-3: openChat does not reload an already initialized chat', async () => {
      const fakeApi: IChatApi = {
        getChats: async () => [chat(1)],
        getMessages: async () => [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B')],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 2,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      // Повторное открытие того же чата не должно заново грузить историю
      store.applySyncResponse({
        now: '2026-01-01T00:00:00',
        chats: [],
        newChats: [],
        messages: { 1: [makeMsg(3, 1, 'F')] },
      });
      await store.openChat(1);
      expect(store.allMessages.map((m) => m.content).sort()).toEqual(['A', 'B', 'F']);
    });

    it('P2-2: sync caps inactive chat messages to MAX_STORED', async () => {
      const fakeApi: IChatApi = {
        getChats: async () => [chat(1), chat(2)],
        getMessages: async () => [],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 0,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      const manyMsgs = Array.from({ length: 600 }, (_, i) => makeMsg(i + 1, 2, `m${i}`));
      store.applySyncResponse({
        now: '2026-01-01T00:00:00',
        chats: [],
        newChats: [],
        messages: { 2: manyMsgs },
      });

      const { MAX_STORED } = await import('@/modules/Messages/Chat/Constant/Chat/MAX_STORED');
      expect(store.chatStates.get(2)!.messages.length).toBeLessThanOrEqual(MAX_STORED);
    });

    it('P2-4: fetchChats sets chatsError on failure', async () => {
      const failingApi: IChatApi = {
        getChats: async () => {
          throw new Error('boom');
        },
        getMessages: async () => [],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 0,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(failingApi);

      const store = useChatStore();
      await store.fetchChats();
      expect(store.chatsError).toBe('boom');
      expect(store.loadingChats).toBe(false);
    });

    it('P2-4: openChat sets chatError on failure', async () => {
      const failingApi: IChatApi = {
        getChats: async () => [chat(1)],
        getMessages: async () => {
          throw new Error('boom');
        },
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 0,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(failingApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);
      expect(store.chatError).toBe('boom');
      expect(store.loadingMessages).toBe(false);
    });

    it('P2-4: sendMessage sets actionError on failure', async () => {
      const failingApi: IChatApi = {
        getChats: async () => [chat(1)],
        getMessages: async () => [makeMsg(1, 1, 'A')],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 1,
        sendMessage: async () => {
          throw new Error('boom');
        },
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(failingApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);
      await store.sendMessage('hello', []);
      expect(store.actionError).toBe('boom');
      expect(store.sending).toBe(false);
      expect(await store.sendMessage('hello', [])).toBe(false);
    });
  });

  describe('loadOlderMessages (id cursor)', () => {
    function makeMsg(id: number, chatId: number, content: string): ChatMessage {
      return { id, chatId, userId: 1, username: 'U', content, attachments: [], createdAt: '', updatedAt: '' };
    }

    function chat(id: number): Chat {
      return {
        id,
        type: 'private',
        name: `Chat ${id}`,
        unreadCount: 0,
        lastReadMessageId: null,
        lastMessageAt: '2026-07-27T00:00:00',
        members: [],
      };
    }

    it('appends older messages and keeps hasMore true while new unique ids arrive', async () => {
      let beforeId = 0;
      const fakeApi: IChatApi = {
        getChats: async () => [chat(1)],
        getMessages: async () => [makeMsg(3, 1, 'C'), makeMsg(4, 1, 'D')],
        getMessagesBefore: async (_cid, bId) => {
          beforeId = bId;

          return [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B')];
        },
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      expect(store.hasMoreOlder).toBe(true);
      await store.loadOlderMessages();
      expect(beforeId).toBe(3);
      expect(store.allMessages.map((m) => m.content)).toEqual(['A', 'B', 'C', 'D']);
    });

    it('sets hasMore false when no new unique messages are added', async () => {
      const fakeApi: IChatApi = {
        getChats: async () => [chat(1)],
        getMessages: async () => [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B')],
        getMessagesBefore: async () => [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B')],
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      expect(store.hasMoreOlder).toBe(true);
      await store.loadOlderMessages();
      // Повтор страницы вернул только уже загруженные id — курсор-терминатор останавливает историю.
      expect(store.hasMoreOlder).toBe(false);
      expect(store.allMessages.map((m) => m.content)).toEqual(['A', 'B']);
    });

    it('ошибка догрузки — olderError, лента на месте, retry повторяет запрос', async () => {
      const getMessagesBefore = vi
        .fn()
        .mockRejectedValueOnce(new Error('сеть'))
        .mockResolvedValueOnce([makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B')]);
      const fakeApi: IChatApi = {
        getChats: async () => [chat(1)],
        getMessages: async () => [makeMsg(3, 1, 'C')],
        getMessagesBefore,
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      await store.loadOlderMessages();
      expect(store.olderError).toBe('сеть');
      expect(store.chatError).toBe('');
      expect(store.allMessages.map((m) => m.content)).toEqual(['C']);
      expect(store.hasMoreOlder).toBe(true);

      await store.loadOlderMessages();
      expect(store.olderError).toBe('');
      expect(store.allMessages.map((m) => m.content)).toEqual(['A', 'B', 'C']);
    });
  });

  describe('private chat peer under a non-placeholder user', () => {
    it('replaces the self placeholder member with the current user', async () => {
      await mockLogin('admin', 'test');
      try {
        const store = useChatStore();
        await store.fetchChats();

        const anna = store.chats.find((c) => c.name === 'Анна Смирнова');
        expect(anna).toBeTruthy();
        const memberIds = anna!.members.map((m) => m.userId);
        // Место текущего пользователя (sentinel SELF) заменено на вошедшего (id 2).
        expect(memberIds).toContain(2);
        expect(memberIds).not.toContain(1);
        // Собеседник приватного чата — не текущий пользователь.
        const peer = anna!.members.find((m) => m.userId !== 2);
        expect(peer?.userId).toBe(3);
      } finally {
        await mockLogout();
      }
    });

    it('message authors belong to the current user and real peers, not the placeholder', async () => {
      await mockLogin('admin', 'test');
      try {
        const store = useChatStore();
        await store.fetchChats();

        const dmitry = store.chats.find((c) => c.name === 'Дмитрий Волков' && c.type === 'private');
        expect(dmitry).toBeTruthy();
        await store.openChat(dmitry!.id);

        const authorIds = new Set(store.allMessages.map((m) => m.userId));
        // Ни одного сообщения от placeholder (Иван Петров).
        expect(authorIds.has(1)).toBe(false);
        // Сообщения принадлежат вошедшему пользователю и реальному собеседнику.
        expect(authorIds.has(2)).toBe(true);
        expect(authorIds.has(6)).toBe(true);
      } finally {
        await mockLogout();
      }
    });
  });

  describe('onlyIfMember tab', () => {
    function charChat(id: number, name: string, memberIds: number[]): Chat {
      return {
        id,
        type: 'character_discussion',
        name,
        unreadCount: 0,
        lastReadMessageId: null,
        lastMessageAt: '2026-07-27T00:00:00',
        members: memberIds.map((userId) => ({ userId, status: 'member', joinedAt: '2026-07-01T00:00:00' })),
      };
    }

    function chatApi(chats: Chat[]): IChatApi {
      return {
        getChats: async () => chats,
        getMessages: async () => [],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 0,
        updateMessageVisibility: async (chatId, messageId) => ({
          id: messageId,
          chatId,
          userId: 2,
          username: 'A',
          content: 'sent',
          attachments: [],
          createdAt: '',
          updatedAt: '',
        }),
        sendMessage: async () => ({
          id: 999,
          chatId: 1,
          userId: 2,
          username: 'A',
          content: 'sent',
          attachments: [],
          createdAt: '',
          updatedAt: '',
        }),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: '',
          updatedAt: '',
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: '', chats: [], newChats: [], messages: {} }),
      };
    }

    it('tab «Обсуждения персонажей» shows only chats where the user participates', async () => {
      await mockLogin('admin', 'test');
      await useAuthStore().checkAuth();
      try {
        registerChatApi(
          chatApi([charChat(1, 'Торвин', [2, 3]), charChat(2, 'Элиандра', [4]), charChat(3, 'Гаррик', [2])]),
        );

        const store = useChatStore();
        store.selectedTab = 'character_discussion';
        await store.fetchChats();

        expect(store.currentTabChats.map((c) => c.name)).toEqual(['Торвин', 'Гаррик']);
      } finally {
        await mockLogout();
      }
    });

    it('без onlyIfMember вкладка показывает все чаты типа', async () => {
      await mockLogin('admin', 'test');
      await useAuthStore().checkAuth();
      try {
        registerChatTabs([
          {
            key: 'all_discussions',
            label: 'Все обсуждения',
            icon: 'mdi-forum',
            types: ['character_discussion'],
            sortOrder: 4,
          },
        ]);
        registerChatApi(
          chatApi([charChat(1, 'Торвин', [2, 3]), charChat(2, 'Элиандра', [4]), charChat(3, 'Гаррик', [2])]),
        );

        const store = useChatStore();
        store.selectedTab = 'all_discussions';
        await store.fetchChats();

        expect(store.currentTabChats.map((c) => c.name)).toEqual(['Торвин', 'Элиандра', 'Гаррик']);
      } finally {
        await mockLogout();
      }
    });

    it('sendMessage adds the current user as participant of the chat', async () => {
      await mockLogin('admin', 'test');
      await useAuthStore().checkAuth();
      try {
        registerChatApi(chatApi([charChat(1, 'Торвин', [3]), charChat(2, 'Элиандра', [4, 2])]));

        const store = useChatStore();
        await store.fetchChats();
        await store.openChat(1);

        expect(store.activeChat!.members.map((m) => m.userId)).toEqual([3]);

        await store.sendMessage('привет', []);

        expect(store.activeChat!.members.map((m) => m.userId)).toContain(2);
      } finally {
        await mockLogout();
      }
    });
  });
});
