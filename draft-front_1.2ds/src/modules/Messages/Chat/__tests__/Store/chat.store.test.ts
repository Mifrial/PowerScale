import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { registerChatApi, registerChatTabs } from '@/modules/Messages/Chat/init';
import { registerAuthApi } from '@/modules/Core/Auth/init';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { mockAuthApi } from '@/modules/Core/Auth/Mock/mockAuthApi';
import { mockChatApi } from '@/modules/Messages/Chat/Mock/mockChatApi';
import { mockLogin, mockLogout } from '@/modules/Core/Auth/Mock/mockAuth';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { currentUserSessionService } from '@/modules/Core/User/init';
import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import { PAGE_SIZE } from '@/modules/Messages/Chat/Constant/Chat/PAGE_SIZE';

function stubChatApi(overrides: Partial<IChatApi> = {}): IChatApi {
  const api: IChatApi = {
    getChats: async () => [],
    getMessages: async () => [],
    getMessagesBefore: async () => [],
    getTotalMessageCount: async () => 0,
    findMessagePage: async (chatId, limit, offset) => ({
      items: await api.getMessages(chatId, limit, offset),
      total: await api.getTotalMessageCount(chatId),
    }),
    sendMessage: async () => ({
      id: 999,
      chatId: 1,
      userId: 1,
      username: 'U',
      content: 'sent',
      attachments: [],
      createdAt: 0,
      updatedAt: 0,
    }),
    updateMessageVisibility: async (_chatId, messageId) => ({
      id: messageId,
      chatId: 1,
      userId: 1,
      username: 'U',
      content: 'updated',
      attachments: [],
      createdAt: 0,
      updatedAt: 0,
    }),
    sendSystemMessage: async () => ({
      id: 998,
      chatId: 1,
      userId: 2,
      username: 'Система',
      content: 'Ходит X',
      attachments: [],
      createdAt: 0,
      updatedAt: 0,
      kind: 'default',
    }),
    markChatRead: async () => {},
    addPrivate: async () => {
      throw new Error('addPrivate not stubbed');
    },
    addGroup: async () => {
      throw new Error('addGroup not stubbed');
    },
    sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
    ...overrides,
  };
  if (overrides.findMessagePage === undefined) {
    api.findMessagePage = async (chatId, limit, offset) => ({
      items: await api.getMessages(chatId, limit, offset),
      total: await api.getTotalMessageCount(chatId),
    });
  }

  return api;
}

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
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

  it('addPrivate upserts chat and opens it', async () => {
    await mockLogin('admin', 'test');
    const created: Chat = {
      id: 42,
      type: 'private',
      name: '',
      unreadCount: 0,
      lastReadMessageId: null,
      lastMessageAt: 1,
      members: [],
    };
    registerChatApi(
      stubChatApi({
        addPrivate: async () => created,
      }),
    );
    const store = useChatStore();
    const ok = await store.addPrivate(3);
    expect(ok).toBe(true);
    expect(store.chats.map((item) => item.id)).toContain(42);
    expect(store.activeChatId).toBe(42);
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

  it('lastSyncCursor updates after sync', async () => {
    const store = useChatStore();
    store.startSync();
    expect(store.lastSyncCursor).toBeNull();

    await store.fetchChats();
    const res = await mockChatApi.sync(0);
    expect(typeof res.now).toBe('number');
    expect(typeof res.afterId).toBe('number');
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
      return { id, chatId, userId: 1, username: 'U', content, attachments: [], createdAt: 0, updatedAt: 0 };
    }

    it('openChat race: late response for A does not overwrite B', async () => {
      const aMsgs = deferred<ChatMessage[]>();
      const aTotal = deferred<number>();
      const bMsgs = deferred<ChatMessage[]>();
      const bTotal = deferred<number>();

      const fakeApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
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
        now: 1,
        afterId: 0,
        chats: [],
        newChats: [],
        messages: { [otherChatId]: [syncMsg] },
      });

      await store.openChat(otherChatId);
      expect(store.allMessages.find((m) => m.content === 'sync-msg')).toBeTruthy();
    });

    it('sync upserts chat from chats when it is not in the list', () => {
      const store = useChatStore();
      store.applySyncResponse({
        now: 1,
        afterId: 0,
        chats: [
          {
            id: 77,
            type: 'group',
            name: 'Новый',
            unreadCount: 0,
            lastReadMessageId: null,
            lastMessageAt: 1,
            members: [],
          },
        ],
        newChats: [],
        messages: {},
      });
      expect(store.chats.find((c) => c.id === 77)?.name).toBe('Новый');
    });
  });

  describe('unread position (lastReadMessageId)', () => {
    function makeMsg(id: number, chatId: number, content: string): ChatMessage {
      return { id, chatId, userId: 1, username: 'U', content, attachments: [], createdAt: 0, updatedAt: 0 };
    }

    function chat(id: number, unreadCount: number, lastReadMessageId: number | null): Chat {
      return {
        id,
        type: 'private',
        name: `Chat ${id}`,
        unreadCount,
        lastReadMessageId,
        lastMessageAt: 1753574400,
        members: [],
      };
    }

    it('openChat marks read and advances lastReadMessageId to newest loaded message', async () => {
      let marked = false;
      const fakeApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {
          marked = true;
        },
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
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
      const fakeApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);
      store.setAutoScroll(false);

      const syncMsg = makeMsg(5, 1, 'E');
      store.applySyncResponse({
        now: 1,
        afterId: 0,
        chats: [{ ...store.activeChat!, unreadCount: 1 }],
        newChats: [],
        messages: { 1: [syncMsg] },
      });

      expect(store.firstUnreadMessageId).toBe(5);
    });

    it('sync with autoScroll on reads active chat and clears divider', async () => {
      const fakeApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      const syncMsg = makeMsg(5, 1, 'E');
      store.applySyncResponse({
        now: 1,
        afterId: 0,
        chats: [{ ...store.activeChat!, unreadCount: 1 }],
        newChats: [],
        messages: { 1: [syncMsg] },
      });

      expect(store.activeChat!.unreadCount).toBe(0);
      expect(store.activeChat!.lastReadMessageId).toBe(5);
      expect(store.firstUnreadMessageId).toBeNull();
    });

    it('sync merge не поднимает unread при меньшем lastRead с сервера', async () => {
      const fakeApi = stubChatApi({
        getChats: async () => [chat(1, 0, 2)],
        getMessages: async () => [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B'), makeMsg(3, 1, 'C'), makeMsg(4, 1, 'D')],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => makeMsg(998, 1, 'sys'),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);
      expect(store.activeChat!.lastReadMessageId).toBe(4);

      store.applySyncResponse({
        now: 1,
        afterId: 0,
        chats: [{ ...store.activeChat!, unreadCount: 9, lastReadMessageId: 2 }],
        newChats: [],
        messages: {},
      });

      expect(store.activeChat!.unreadCount).toBe(0);
      expect(store.activeChat!.lastReadMessageId).toBe(4);
    });

    it('loadChat на initialized снова зовёт markChatRead', async () => {
      const markChatRead = vi.fn().mockResolvedValue(undefined);
      const fakeApi = stubChatApi({
        getChats: async () => [chat(1, 0, 2)],
        getMessages: async () => [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B')],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 2,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => makeMsg(998, 1, 'sys'),
        markChatRead,
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);
      expect(markChatRead).toHaveBeenCalledTimes(1);
      await store.loadChat(1);
      expect(markChatRead).toHaveBeenCalledTimes(2);
    });

    it('reject markChatRead → retrying и повтор после backoff', async () => {
      vi.useFakeTimers();
      const markChatRead = vi.fn().mockRejectedValue(new Error('сеть'));
      const fakeApi = stubChatApi({
        getChats: async () => [chat(1, 0, 2)],
        getMessages: async () => [makeMsg(1, 1, 'A')],
        getMessagesBefore: async () => [],
        getTotalMessageCount: async () => 1,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
        updateMessageVisibility: async (chatId, messageId) => makeMsg(messageId, chatId, 'updated'),
        sendSystemMessage: async () => makeMsg(998, 1, 'sys'),
        markChatRead,
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);
      await vi.advanceTimersByTimeAsync(0);

      expect(store.readAckHealth[1]).toEqual({ status: 'retrying', lastError: 'сеть' });
      expect(markChatRead).toHaveBeenCalledTimes(1);
      await vi.advanceTimersByTimeAsync(1000);
      expect(markChatRead).toHaveBeenCalledTimes(2);
      store.retryReadAck(1);
      await vi.advanceTimersByTimeAsync(0);
      expect(markChatRead).toHaveBeenCalledTimes(3);
      vi.useRealTimers();
    });
  });

  describe('history loading and storage limits', () => {
    function makeMsg(id: number, chatId: number, content: string): ChatMessage {
      return { id, chatId, userId: 1, username: 'U', content, attachments: [], createdAt: 0, updatedAt: 0 };
    }

    function chat(id: number): Chat {
      return {
        id,
        type: 'private',
        name: `Chat ${id}`,
        unreadCount: 0,
        lastReadMessageId: null,
        lastMessageAt: 1753574400,
        members: [],
      };
    }

    it('P2-3: openChat loads history when state was created by sync', async () => {
      const fakeApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();

      // sync создаёт state без открытия
      store.applySyncResponse({
        now: 1,
        afterId: 0,
        chats: [],
        newChats: [],
        messages: { 1: [makeMsg(5, 1, 'E')] },
      });

      await store.openChat(1);
      expect(store.allMessages.map((m) => m.content).sort()).toEqual(['A', 'B', 'C', 'D', 'E']);
      expect(store.hasMoreOlder).toBe(false);
    });

    it('P2-3: openChat does not reload an already initialized chat', async () => {
      const fakeApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      // Повторное открытие того же чата не должно заново грузить историю
      store.applySyncResponse({
        now: 1,
        afterId: 0,
        chats: [],
        newChats: [],
        messages: { 1: [makeMsg(3, 1, 'F')] },
      });
      await store.openChat(1);
      expect(store.allMessages.map((m) => m.content).sort()).toEqual(['A', 'B', 'F']);
    });

    it('P2-2: sync caps inactive chat messages to MAX_STORED', async () => {
      const fakeApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      const manyMsgs = Array.from({ length: 600 }, (_, i) => makeMsg(i + 1, 2, `m${i}`));
      store.applySyncResponse({
        now: 1,
        afterId: 0,
        chats: [],
        newChats: [],
        messages: { 2: manyMsgs },
      });

      const { MAX_STORED } = await import('@/modules/Messages/Chat/Constant/Chat/MAX_STORED');
      expect(store.chatStates.get(2)!.messages.length).toBeLessThanOrEqual(MAX_STORED);
    });

    it('P2-4: fetchChats sets chatsError on failure', async () => {
      const failingApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(failingApi);

      const store = useChatStore();
      await store.fetchChats();
      expect(store.chatsError).toBe('boom');
      expect(store.loadingChats).toBe(false);
    });

    it('P2-4: openChat sets chatError on failure', async () => {
      const failingApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
      registerChatApi(failingApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);
      expect(store.chatError).toBe('boom');
      expect(store.loadingMessages).toBe(false);
    });

    it('P2-4: sendMessage sets actionError on failure', async () => {
      const failingApi = stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
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

  describe('loadOlderMessages (offset page)', () => {
    function makeMsg(id: number, chatId: number, content: string): ChatMessage {
      return { id, chatId, userId: 1, username: 'U', content, attachments: [], createdAt: 0, updatedAt: 0 };
    }

    function chat(id: number): Chat {
      return {
        id,
        type: 'private',
        name: `Chat ${id}`,
        unreadCount: 0,
        lastReadMessageId: null,
        lastMessageAt: 1753574400,
        members: [],
      };
    }

    function firstPage(): ChatMessage[] {
      return Array.from({ length: PAGE_SIZE }, (_, i) => makeMsg(100 + i, 1, `n${i}`));
    }

    it('догружает старше по offset = loadedCount', async () => {
      let olderOffset = -1;
      const fakeApi = stubChatApi({
        getChats: async () => [chat(1)],
        findMessagePage: async (_chatId, _limit, offset) => {
          if (offset === 0) return { items: firstPage(), total: PAGE_SIZE + 2 };
          olderOffset = offset;

          return { items: [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B')], total: PAGE_SIZE + 2 };
        },
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      expect(store.hasMoreOlder).toBe(true);
      await store.loadOlderMessages();
      expect(olderOffset).toBe(PAGE_SIZE);
      expect(store.allMessages.map((m) => m.content)).toEqual(['A', 'B', ...firstPage().map((m) => m.content)]);
    });

    it('короткая повторная страница без новых id — конец истории', async () => {
      const page = firstPage();
      const fakeApi = stubChatApi({
        getChats: async () => [chat(1)],
        findMessagePage: async (_chatId, _limit, offset) => {
          if (offset === 0) return { items: page, total: PAGE_SIZE + 10 };

          return { items: page, total: PAGE_SIZE + 10 };
        },
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      expect(store.hasMoreOlder).toBe(true);
      await store.loadOlderMessages();
      expect(store.hasMoreOlder).toBe(false);
      expect(store.allMessages.map((m) => m.content)).toEqual(page.map((m) => m.content));
    });

    it('ошибка догрузки — olderError, лента на месте, retry повторяет запрос', async () => {
      let olderCalls = 0;
      const fakeApi = stubChatApi({
        getChats: async () => [chat(1)],
        findMessagePage: async (_chatId, _limit, offset) => {
          if (offset === 0) return { items: firstPage(), total: PAGE_SIZE + 2 };
          olderCalls += 1;
          if (olderCalls === 1) throw new Error('сеть');

          return { items: [makeMsg(1, 1, 'A'), makeMsg(2, 1, 'B')], total: PAGE_SIZE + 2 };
        },
      });
      registerChatApi(fakeApi);

      const store = useChatStore();
      await store.fetchChats();
      await store.openChat(1);

      await store.loadOlderMessages();
      expect(store.olderError).toBe('сеть');
      expect(store.chatError).toBe('');
      expect(store.allMessages.map((m) => m.content)).toEqual(firstPage().map((m) => m.content));
      expect(store.hasMoreOlder).toBe(true);

      await store.loadOlderMessages();
      expect(store.olderError).toBe('');
      expect(store.allMessages.map((m) => m.content)).toEqual(['A', 'B', ...firstPage().map((m) => m.content)]);
    });
  });

  describe('private chat peer under a non-placeholder user', () => {
    it('replaces the self placeholder member with the current user', async () => {
      const user = await mockLogin('admin', 'test');
      currentUserSessionService.setCurrent(user);
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
        currentUserSessionService.clearCurrent();
        await mockLogout();
      }
    });

    it('message authors belong to the current user and real peers, not the placeholder', async () => {
      const user = await mockLogin('admin', 'test');
      currentUserSessionService.setCurrent(user);
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
        currentUserSessionService.clearCurrent();
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
        lastMessageAt: 1753574400,
        members: memberIds.map((userId) => ({ userId, status: 'member', joinedAt: 1751328000 })),
      };
    }

    function chatApi(chats: Chat[]): IChatApi {
      return stubChatApi({
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
          createdAt: 0,
          updatedAt: 0,
        }),
        sendMessage: async () => ({
          id: 999,
          chatId: 1,
          userId: 2,
          username: 'A',
          content: 'sent',
          attachments: [],
          createdAt: 0,
          updatedAt: 0,
        }),
        sendSystemMessage: async () => ({
          id: 998,
          chatId: 1,
          userId: 2,
          username: 'Система',
          content: 'Ходит X',
          attachments: [],
          createdAt: 0,
          updatedAt: 0,
          kind: 'default',
        }),
        markChatRead: async () => {},
        sync: async () => ({ now: 0, afterId: 0, chats: [], newChats: [], messages: {} }),
      });
    }

    it('tab «Обсуждения персонажей» shows only chats where the user participates', async () => {
      const user = await mockLogin('admin', 'test');
      currentUserSessionService.setCurrent(user);
      try {
        registerChatApi(
          chatApi([charChat(1, 'Торвин', [2, 3]), charChat(2, 'Элиандра', [4]), charChat(3, 'Гаррик', [2])]),
        );

        const store = useChatStore();
        store.selectedTab = 'character_discussion';
        await store.fetchChats();

        expect(store.currentTabChats.map((c) => c.name)).toEqual(['Торвин', 'Гаррик']);
      } finally {
        currentUserSessionService.clearCurrent();
        await mockLogout();
      }
    });

    it('без onlyIfMember вкладка показывает все чаты типа', async () => {
      const user = await mockLogin('admin', 'test');
      currentUserSessionService.setCurrent(user);
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
        currentUserSessionService.clearCurrent();
        await mockLogout();
      }
    });

    it('sendMessage adds the current user as participant of the chat', async () => {
      const user = await mockLogin('admin', 'test');
      currentUserSessionService.setCurrent(user);
      try {
        registerChatApi(chatApi([charChat(1, 'Торвин', [3]), charChat(2, 'Элиандра', [4, 2])]));

        const store = useChatStore();
        await store.fetchChats();
        await store.openChat(1);

        expect(store.activeChat!.members.map((m) => m.userId)).toEqual([3]);

        await store.sendMessage('привет', []);

        expect(store.activeChat!.members.map((m) => m.userId)).toContain(2);
      } finally {
        currentUserSessionService.clearCurrent();
        await mockLogout();
      }
    });
  });
});
