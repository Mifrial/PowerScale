import { describe, it, expect } from 'vitest';
import {
  mockGetChats,
  mockGetTotalMessageCount,
  mockGetMessages,
  mockSendMessage,
  mockSendSystemMessage,
  mockSetChatMembers,
  mockUpdateMessageVisibility,
} from '@/modules/Messages/Chat/Mock/mockChat';
import { mockLogin, mockLogout } from '@/modules/Core/Auth/Mock/mockAuth';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';

describe('mock chat fixtures (single source of truth)', () => {
  it('every chat has messages and derived preview matches the last message', async () => {
    const chats = await mockGetChats();

    expect(chats.length).toBeGreaterThan(0);

    const results = await Promise.all(
      chats.map(async (chat) => {
        const total = await mockGetTotalMessageCount(chat.id);
        const messages = await mockGetMessages(chat.id, total, 0);

        return { chat, total, messages };
      }),
    );

    for (const { chat, total, messages } of results) {
      expect(total, `chat ${chat.id} ${chat.name}`).toBeGreaterThan(0);
      expect(messages.length).toBe(total);

      const last = messages[messages.length - 1];
      const expectedPreview = last.content || (last.attachments.length ? `${last.attachments.length} вложен.` : '');

      expect(chat.lastMessage, `preview chat ${chat.id}`).toBe(expectedPreview);
      expect(chat.lastMessageAt, `lastMessageAt chat ${chat.id}`).toBe(last.createdAt);
    }
  });

  it('unreadCount equals messages newer than lastReadMessageId', async () => {
    const chats = await mockGetChats();

    const results = await Promise.all(
      chats.map(async (chat) => {
        const total = await mockGetTotalMessageCount(chat.id);
        const messages = await mockGetMessages(chat.id, total, 0);

        return { chat, messages };
      }),
    );

    for (const { chat, messages } of results) {
      const expectedUnread =
        chat.lastReadMessageId == null
          ? messages.length
          : messages.filter((m) => m.id > chat.lastReadMessageId!).length;

      expect(chat.unreadCount, `unread chat ${chat.id}`).toBe(expectedUnread);
    }
  });

  it('demo messages with inline user/rule chips exist for manual testing', async () => {
    const chats = await mockGetChats();
    const results = await Promise.all(
      chats.map(async (chat) => {
        const total = await mockGetTotalMessageCount(chat.id);

        return mockGetMessages(chat.id, total, 0);
      }),
    );
    const contents = results.flat().map((m) => m.content);

    expect(contents.some((c) => c.includes('[[user:'))).toBe(true);
    expect(contents.some((c) => c.includes('[[rule:'))).toBe(true);
  });

  it('game chat fixtures carry speaker for character/gm messages', async () => {
    const total = await mockGetTotalMessageCount(1);
    const messages = await mockGetMessages(1, total, 0);
    const withSpeaker = messages.filter((m) => m.speaker);

    expect(withSpeaker.length).toBeGreaterThan(0);
    expect(withSpeaker.some((m) => m.speaker?.kind === 'character')).toBe(true);
    expect(withSpeaker.some((m) => m.speaker?.kind === 'gm')).toBe(true);
  });

  it('sendMessage stores speaker and it round-trips through getMessages', async () => {
    const chatId = 1;
    const speaker: ChatSpeaker = { kind: 'character', characterId: 42, characterName: 'Тестовик' };
    const created = await mockSendMessage(chatId, 'От лица персонажа', [], speaker);

    expect(created.speaker).toEqual(speaker);

    const total = await mockGetTotalMessageCount(chatId);
    const all = await mockGetMessages(chatId, total, 0);
    expect(all.find((m) => m.id === created.id)?.speaker).toEqual(speaker);
  });

  it('sendSystemMessage creates a kind=default message that round-trips', async () => {
    const chatId = 1;
    const created = await mockSendSystemMessage(chatId, 'Ходит Гаррик');

    expect(created.kind).toBe('default');
    expect(created.content).toBe('Ходит Гаррик');

    const total = await mockGetTotalMessageCount(chatId);
    const all = await mockGetMessages(chatId, total, 0);
    const stored = all.find((m) => m.id === created.id);
    expect(stored?.kind).toBe('default');
  });

  it('sendMessage stores visibility and it round-trips without filtering', async () => {
    const chatId = 1;
    const visibility = { all: false, forRole: 'gm' };
    const created = await mockSendMessage(chatId, 'Скрытое сообщение', [], undefined, visibility);

    expect(created.visibility).toEqual(visibility);

    const total = await mockGetTotalMessageCount(chatId);
    const all = await mockGetMessages(chatId, total, 0);
    expect(all.find((m) => m.id === created.id)?.visibility).toEqual(visibility);
  });

  it('mockSetChatMembers заменяет участников чата ролями', async () => {
    mockSetChatMembers(1, [
      { userId: 1, role: 'gm' },
      { userId: 2, role: 'player' },
    ]);
    const chats = await mockGetChats();
    const chat = chats.find((c) => c.id === 1);
    const members = chat?.members.map((m) => ({ userId: m.userId, role: m.role }));

    expect(members).toEqual([
      { userId: 1, role: 'gm' },
      { userId: 2, role: 'player' },
    ]);
  });

  it('скрытие на уровне мока: ГМ и отправитель видят gm-only, игрок — нет', async () => {
    mockSetChatMembers(2, [
      { userId: 1, role: 'gm' },
      { userId: 2, role: 'player' },
    ]);
    await mockSendMessage(2, 'Секрет для ГМ', [], undefined, { all: false, forRole: 'gm' });

    // ГМ (текущий пользователь 1) видит сообщение.
    const asGm = await mockGetMessages(2, 10, 0);
    expect(asGm.some((m) => m.content === 'Секрет для ГМ')).toBe(true);

    try {
      // Игрок (id 2) не получает скрытое сообщение.
      await mockLogin('admin', 'test');
      const asPlayer = await mockGetMessages(2, 10, 0);
      expect(asPlayer.some((m) => m.content === 'Секрет для ГМ')).toBe(false);
    } finally {
      await mockLogout();
    }
  });

  it('отправитель всегда видит своё скрытое сообщение', async () => {
    mockSetChatMembers(1, [
      { userId: 1, role: 'gm' },
      { userId: 2, role: 'player' },
    ]);
    // Игрок 2 пишет whisper ГМ; игрок 2 — отправитель → видит своё.
    try {
      await mockLogin('admin', 'test');
      await mockSendMessage(1, 'Шёпот ГМ', [], undefined, { all: false, forRole: 'gm' });
      const all = await mockGetMessages(1, 10, 0);
      expect(all.some((m) => m.content === 'Шёпот ГМ')).toBe(true);
    } finally {
      await mockLogout();
    }
  });

  it('updateMessageVisibility меняет видимость и снимает её', async () => {
    mockSetChatMembers(2, [
      { userId: 1, role: 'gm' },
      { userId: 2, role: 'player' },
    ]);
    const sent = await mockSendMessage(2, 'меняю видимость', [], undefined, { all: false, forRole: 'gm' });

    const targeted = await mockUpdateMessageVisibility(2, sent.id, { all: false, forUsers: [2] });
    expect(targeted.visibility).toEqual({ all: false, forUsers: [2] });

    const cleared = await mockUpdateMessageVisibility(2, sent.id, undefined);
    expect(cleared.visibility).toBeUndefined();
  });

  it('после смены на скрытое сообщение не отдаётся не-зрителю', async () => {
    mockSetChatMembers(2, [
      { userId: 1, role: 'gm' },
      { userId: 2, role: 'player' },
    ]);
    const sent = await mockSendMessage(2, 'видимость меняется', [], undefined, { all: false, forRole: 'gm' });
    await mockUpdateMessageVisibility(2, sent.id, { all: false, forUsers: [99] });

    try {
      await mockLogin('admin', 'test');
      const all = await mockGetMessages(2, 10, 0);
      expect(all.some((m) => m.id === sent.id)).toBe(false);
    } finally {
      await mockLogout();
    }
  });
});
