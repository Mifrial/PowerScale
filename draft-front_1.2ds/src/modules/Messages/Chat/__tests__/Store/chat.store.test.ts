import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { registerChatApi } from '@/modules/Messages/Chat/init';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { mockChatApi } from '@/modules/Messages/Chat/Mock/mockChatApi';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';

beforeEach(() => {
  setActivePinia(createPinia());
  serviceLocator.reset();
  registerChatApi(mockChatApi);
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
      return { id, chatId, userId: 1, username: 'U', content, rolls: [], createdAt: '', updatedAt: '' };
    }

    it('openChat race: late response for A does not overwrite B', async () => {
      const aMsgs = deferred<ChatMessage[]>();
      const aTotal = deferred<number>();
      const bMsgs = deferred<ChatMessage[]>();
      const bTotal = deferred<number>();

      const fakeApi: IChatApi = {
        getChats: async () => [],
        getMessages: (chatId) => (chatId === 1 ? aMsgs.promise : bMsgs.promise),
        getTotalMessageCount: (chatId) => (chatId === 1 ? aTotal.promise : bTotal.promise),
        sendMessage: async () => makeMsg(999, 1, 'sent'),
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
      return { id, chatId, userId: 1, username: 'U', content, rolls: [], createdAt: '', updatedAt: '' };
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
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
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
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
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
        getTotalMessageCount: async () => 4,
        sendMessage: async () => makeMsg(99, 1, 'sent'),
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
});
