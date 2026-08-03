import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { MemberInfo } from '@/modules/Messages/Chat/Dto/MemberInfo';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import { rollService } from '@/modules/Roleplay/Game/Service/RollService';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));
let msgIdSeq = 50;

function userName(u: { name: string; surname?: string }): string {
  return [u.name, u.surname].filter(Boolean).join(' ');
}

function member(userId: number, status: string): MemberInfo {
  return { userId, status, joinedAt: '2026-06-01T00:00:00' };
}

const mockChats: Chat[] = [
  {
    id: 1,
    type: 'game',
    name: 'Подземелье дракона',
    unreadCount: 3,
    lastReadMessageId: 2,
    lastMessage: 'Кидай инициативу',
    lastMessageAt: '2026-07-27T14:15:00',
    members: [member(1, 'gm'), member(3, 'member'), member(4, 'member'), member(5, 'member')],
  },
  {
    id: 6,
    type: 'group',
    name: 'Ведущие PowerScale',
    unreadCount: 2,
    lastReadMessageId: null,
    lastMessage: 'Обновление правил в пятницу',
    lastMessageAt: '2026-07-27T13:00:00',
    members: [member(1, 'admin'), member(3, 'member'), member(5, 'member'), member(7, 'member')],
  },
  {
    id: 4,
    type: 'private',
    name: 'Анна Смирнова',
    unreadCount: 0,
    lastReadMessageId: 32,
    lastMessage: 'Готовь персонажа',
    lastMessageAt: '2026-07-27T11:00:00',
    members: [member(1, 'member'), member(3, 'member')],
  },
  {
    id: 2,
    type: 'game',
    name: 'Школа волшебства',
    unreadCount: 1,
    lastReadMessageId: 10,
    lastMessage: 'Я применяю заклинание',
    lastMessageAt: '2026-07-27T10:00:00',
    members: [member(1, 'gm'), member(4, 'member'), member(6, 'member')],
  },
  {
    id: 5,
    type: 'private',
    name: 'Пётр Козлов',
    unreadCount: 0,
    lastReadMessageId: 40,
    lastMessage: 'Принято',
    lastMessageAt: '2026-07-26T18:00:00',
    members: [member(1, 'member'), member(4, 'member')],
  },
  {
    id: 3,
    type: 'game_discussion',
    name: 'Обсуждение: Подземелье дракона',
    unreadCount: 0,
    lastReadMessageId: 22,
    lastMessage: 'Может перенесём сессию?',
    lastMessageAt: '2026-07-26T14:00:00',
    members: [member(1, 'member'), member(3, 'member'), member(4, 'member'), member(5, 'member')],
  },
  {
    id: 7,
    type: 'character_discussion',
    name: 'Обсуждение: Мэллорн',
    unreadCount: 1,
    lastReadMessageId: 60,
    lastMessage: 'Проверил, надо исправить навыки',
    lastMessageAt: '2026-07-25T14:00:00',
    members: [member(1, 'member'), member(3, 'member')],
  },
  {
    id: 8,
    type: 'game',
    name: 'Тайны забытого храма',
    unreadCount: 5,
    lastReadMessageId: null,
    lastMessage: 'Я открываю дверь',
    lastMessageAt: '2026-07-27T12:30:00',
    members: [member(3, 'gm'), member(4, 'member'), member(5, 'member'), member(6, 'member')],
  },
  {
    id: 9,
    type: 'private',
    name: 'Дмитрий Волков',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessage: 'Спасибо за игру',
    lastMessageAt: '2026-07-26T22:00:00',
    members: [member(1, 'member'), member(6, 'member')],
  },
  {
    id: 10,
    type: 'group',
    name: 'Мастера подземелий',
    unreadCount: 1,
    lastReadMessageId: null,
    lastMessage: 'Новый модуль вышел',
    lastMessageAt: '2026-07-27T09:00:00',
    members: [member(1, 'member'), member(3, 'member'), member(4, 'member'), member(5, 'member')],
  },
  {
    id: 11,
    type: 'game',
    name: 'Кровавая луна',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessage: 'Срабатывает ловушка',
    lastMessageAt: '2026-07-25T20:00:00',
    members: [member(1, 'gm'), member(5, 'member'), member(6, 'member')],
  },
  {
    id: 12,
    type: 'game_discussion',
    name: 'Обсуждение: Школа волшебства',
    unreadCount: 2,
    lastReadMessageId: null,
    lastMessage: 'Давайте упростим механику зелий',
    lastMessageAt: '2026-07-27T08:00:00',
    members: [member(1, 'member'), member(4, 'member'), member(6, 'member')],
  },
  {
    id: 13,
    type: 'private',
    name: 'Елена Морозова',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessage: 'Договорились',
    lastMessageAt: '2026-07-25T16:00:00',
    members: [member(1, 'member'), member(5, 'member')],
  },
  {
    id: 14,
    type: 'character_discussion',
    name: 'Обсуждение: Гимли',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessage: 'Добавил боевые навыки',
    lastMessageAt: '2026-07-24T19:00:00',
    members: [member(1, 'member'), member(4, 'member')],
  },
  {
    id: 15,
    type: 'game',
    name: 'Ледяная бездна',
    unreadCount: 1,
    lastReadMessageId: null,
    lastMessage: 'Ваша очередь ходить',
    lastMessageAt: '2026-07-26T16:00:00',
    members: [member(3, 'gm'), member(4, 'member'), member(5, 'member'), member(6, 'member')],
  },
  {
    id: 16,
    type: 'group',
    name: 'Ролевое сообщество',
    unreadCount: 7,
    lastReadMessageId: null,
    lastMessage: 'Собираемся в субботу в 15:00',
    lastMessageAt: '2026-07-27T12:00:00',
    members: [
      member(1, 'admin'),
      member(3, 'member'),
      member(4, 'member'),
      member(5, 'member'),
      member(7, 'member'),
      member(6, 'member'),
      member(10, 'member'),
    ],
  },
  {
    id: 17,
    type: 'game',
    name: 'Пираты карибского моря',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessage: 'Поднимаем якорь!',
    lastMessageAt: '2026-07-23T14:00:00',
    members: [member(1, 'gm'), member(4, 'member'), member(6, 'member')],
  },
  {
    id: 18,
    type: 'private',
    name: 'Мария Соколова',
    unreadCount: 3,
    lastReadMessageId: null,
    lastMessage: 'Когда следующий выезд?',
    lastMessageAt: '2026-07-27T07:00:00',
    members: [member(1, 'member'), member(9, 'member')],
  },
  {
    id: 19,
    type: 'game_discussion',
    name: 'Обсуждение: Кровавая луна',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessage: 'Баланс босса норм',
    lastMessageAt: '2026-07-24T12:00:00',
    members: [member(1, 'member'), member(5, 'member'), member(6, 'member')],
  },
  {
    id: 20,
    type: 'character_discussion',
    name: 'Обсуждение: Леголас',
    unreadCount: 1,
    lastReadMessageId: null,
    lastMessage: 'Стрелок из лука готов',
    lastMessageAt: '2026-07-26T20:00:00',
    members: [member(1, 'member'), member(4, 'member'), member(6, 'member')],
  },
  {
    id: 21,
    type: 'game',
    name: 'Врата Балдура',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessage: 'Входим в город',
    lastMessageAt: '2026-07-22T18:00:00',
    members: [member(1, 'gm'), member(4, 'member'), member(6, 'member')],
  },
  {
    id: 22,
    type: 'private',
    name: 'Ольга Новикова',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessage: 'Ок',
    lastMessageAt: '2026-07-22T10:00:00',
    members: [member(1, 'member'), member(7, 'member')],
  },
  {
    id: 23,
    type: 'group',
    name: 'D&D Almanac',
    unreadCount: 4,
    lastReadMessageId: null,
    lastMessage: 'Новый бестиарий загружен',
    lastMessageAt: '2026-07-26T15:00:00',
    members: [
      member(1, 'member'),
      member(3, 'member'),
      member(4, 'member'),
      member(5, 'member'),
      member(7, 'member'),
      member(6, 'member'),
    ],
  },
  {
    id: 24,
    type: 'game',
    name: 'Страна чудес',
    unreadCount: 2,
    lastReadMessageId: null,
    lastMessage: 'Белый кролик убегает',
    lastMessageAt: '2026-07-26T12:00:00',
    members: [member(1, 'gm'), member(3, 'member'), member(4, 'member'), member(5, 'member')],
  },
  {
    id: 25,
    type: 'game_discussion',
    name: 'Обсуждение: Ледяная бездна',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessage: 'Финал через две сессии',
    lastMessageAt: '2026-07-24T20:00:00',
    members: [member(3, 'member'), member(4, 'member'), member(5, 'member'), member(6, 'member')],
  },
  {
    id: 26,
    type: 'group',
    name: 'Общий чат',
    unreadCount: 1,
    lastReadMessageId: null,
    lastMessage: 'Добро пожаловать в PowerScale!',
    lastMessageAt: '2026-07-29T00:00:00',
    visibility: 'public',
    members: [
      member(1, 'admin'),
      member(2, 'admin'),
      member(3, 'member'),
      member(4, 'member'),
      member(5, 'member'),
      member(6, 'member'),
      member(7, 'member'),
      member(8, 'member'),
      member(10, 'member'),
    ],
  },
];

// map synthetic userId to real mockUser id
const userIdMap: Record<number, number> = { 1: 1, 2: 3, 3: 4, 4: 6, 5: 5 };

function mapUser(syntheticId: number) {
  const uid = userIdMap[syntheticId] || 1;
  const u = realUsers.find((x) => x.id === uid);

  return { userId: uid, username: u ? userName(u) : 'Неизвестно' };
}

function msg(
  id: number,
  chatId: number,
  syntheticUserId: number,
  content: string,
  rolls: DiceRollResult[],
  createdAt: string,
): ChatMessage {
  const { userId, username } = mapUser(syntheticUserId);

  return { id, chatId, userId, username, content, rolls, createdAt, updatedAt: createdAt };
}

const rawMessages: Record<number, ChatMessage[]> = {
  1: [
    msg(1, 1, 2, 'Все готовы к сессии?', [], '2026-07-27T14:00:00'),
    msg(2, 1, 1, 'Да, персонаж готов', [], '2026-07-27T14:02:00'),
    msg(
      3,
      1,
      3,
      '',
      [
        {
          spec: { diceCount: 5, dieSize: 1, dieFaces: 6, efficiency: 3, adv: 0, label: 'Поиск ловушек' },
          rolls: [1, 3, 5, 6, 2],
          successes: [2, 1, 0, -1, 1],
          adjustedRolls: [1, 3, 5, 6, 2],
          droppedRolls: [],
          totalSuccesses: 3,
        },
      ],
      '2026-07-27T14:05:00',
    ),
    msg(4, 1, 2, 'Входите в пещеру. Кидайте инициативу', [], '2026-07-27T14:10:00'),
    msg(
      5,
      1,
      1,
      '',
      [
        {
          spec: { diceCount: 4, dieSize: 1, dieFaces: 6, efficiency: 2, adv: 0, label: 'Инициатива' },
          rolls: [2, 4, 1, 5],
          successes: [1, 0, 2, -1],
          adjustedRolls: [2, 4, 1, 5],
          droppedRolls: [],
          totalSuccesses: 2,
        },
      ],
      '2026-07-27T14:11:00',
    ),
  ],
  2: [
    msg(10, 2, 4, 'Начинаем урок зельеварения', [], '2026-07-26T10:00:00'),
    msg(11, 2, 1, 'Я добавляю лунный камень в котёл', [], '2026-07-26T10:05:00'),
  ],
  3: [
    msg(20, 3, 2, 'Кому удобно играть в субботу?', [], '2026-07-25T18:00:00'),
    msg(21, 3, 1, 'Мне норм', [], '2026-07-25T18:30:00'),
    msg(22, 3, 3, 'Может перенесём сессию?', [], '2026-07-26T09:00:00'),
  ],
  4: [
    msg(30, 4, 2, 'Привет! Будешь в новой кампании?', [], '2026-07-24T15:00:00'),
    msg(31, 4, 1, 'Да, интересно', [], '2026-07-24T15:10:00'),
    msg(32, 4, 2, 'Готовь персонажа к пятнице', [], '2026-07-25T10:00:00'),
  ],
  5: [msg(40, 5, 3, 'Я принял приглашение', [], '2026-07-23T12:00:00')],
  6: [
    msg(50, 6, 4, 'Коллеги, обновление правил в пятницу', [], '2026-07-26T11:00:00'),
    msg(51, 6, 5, 'Нужно протестировать новые механики', [], '2026-07-26T11:30:00'),
  ],
  7: [
    msg(60, 7, 2, 'Проверила персонажа Мэллорн', [], '2026-07-25T14:00:00'),
    msg(61, 7, 2, 'Проверил, надо исправить навыки', [], '2026-07-25T14:05:00'),
  ],
};

export async function mockGetChats(): Promise<Chat[]> {
  await delay();

  return [...mockChats];
}

const SYNTHETIC_COUNT = 200;

function ensureSyntheticMessages(chatId: number): ChatMessage[] {
  const existing = rawMessages[chatId];
  if (existing && existing.length >= SYNTHETIC_COUNT) return existing;
  const base = existing || [];
  const needed = SYNTHETIC_COUNT - base.length;
  if (needed <= 0) return base;
  const contentPool = [
    'Проверим этот момент по правилам?',
    'Нужно пересчитать бонусы',
    'Я за, давайте так и оставим',
    'А если попробовать другой подход?',
    'По лору это не совсем так',
    'Хороший ход, я записал',
    'Кидай на проверку',
    'У меня всё готово, можно начинать',
    'Подождите минуту, сверюсь с бестиарием',
    'Отлично, продолжаем',
    'Есть идея, как это обыграть',
    'Я обновил лист персонажа',
    'Коллеги, у кого есть вопросы?',
    'Не забудьте отметить расход ресурсов',
    'Шикарный бросок!',
  ];
  const chatMembers = mockChats.find((c) => c.id === chatId)?.members || [];
  const memberIds = chatMembers.length ? chatMembers.map((m) => m.userId) : [1];
  const baseTime = new Date('2026-06-01T10:00:00').getTime();
  for (let i = 0; i < needed; i++) {
    msgIdSeq++;
    const idx = base.length + i;
    const time = new Date(baseTime + idx * 60000).toISOString();
    const uid = memberIds[idx % memberIds.length];
    const u = realUsers.find((x) => x.id === uid);
    base.push({
      id: msgIdSeq,
      chatId,
      userId: uid,
      username: u ? userName(u) : 'Неизвестно',
      content: contentPool[idx % contentPool.length],
      rolls: [],
      createdAt: time,
      updatedAt: time,
    });
  }
  rawMessages[chatId] = base;

  return base;
}

export async function mockGetMessages(chatId: number, limit = 20, offset = 0): Promise<ChatMessage[]> {
  await delay();
  const all = ensureSyntheticMessages(chatId);
  const start = Math.max(0, all.length - offset - limit);
  const end = all.length - offset;

  return all.slice(start, end);
}

export async function mockGetTotalMessageCount(chatId: number): Promise<number> {
  await delay(50);

  return ensureSyntheticMessages(chatId).length;
}

export async function mockSendMessage(chatId: number, content: string, rolls: DiceRollSpec[]): Promise<ChatMessage> {
  await delay(200);
  msgIdSeq++;
  const computedRolls: DiceRollResult[] = rolls.map((spec) => rollService.computeRollResult(spec));
  const now = new Date().toISOString();
  const me = realUsers.find((x) => x.id === 1);
  const msg: ChatMessage = {
    id: msgIdSeq,
    chatId,
    userId: 1,
    username: me ? userName(me) : 'Я',
    content,
    rolls: computedRolls,
    createdAt: now,
    updatedAt: now,
  };
  if (!rawMessages[chatId]) rawMessages[chatId] = [];
  rawMessages[chatId].push(msg);

  return msg;
}

export async function mockMarkChatRead(chatId: number): Promise<void> {
  await delay(50);
  const chat = mockChats.find((c) => c.id === chatId);
  if (chat) {
    chat.unreadCount = 0;
    const msgs = ensureSyntheticMessages(chatId);
    chat.lastReadMessageId = msgs.length ? msgs[msgs.length - 1].id : null;
  }
}

let syncCallCount = 0;

export async function mockSync(_since: string): Promise<SyncResponse> {
  await delay(100);
  syncCallCount++;

  const now = new Date();
  const updates: Chat[] = [];
  const newChats: Chat[] = [];
  const messages: Record<number, ChatMessage[]> = {};

  if (syncCallCount % 4 === 0) {
    const targetChat = mockChats[Math.floor(Math.random() * mockChats.length)];
    if (!rawMessages[targetChat.id]) rawMessages[targetChat.id] = [];

    msgIdSeq++;
    const members = targetChat.members || [];
    const randomMember = members[Math.floor(Math.random() * members.length)];
    const uid = randomMember ? randomMember.userId : 1;
    const u = realUsers.find((x) => x.id === uid);
    const contentPool = [
      'Я согласен!',
      'Интересный поворот',
      'Давайте уточним детали',
      'Нужна пауза',
      'Отличная идея',
      'Я за',
      'Кинул кости, смотри результат',
      'Подготовьтесь к следующей сцене',
    ];
    const msg: ChatMessage = {
      id: msgIdSeq,
      chatId: targetChat.id,
      userId: uid,
      username: u ? userName(u) : 'Неизвестно',
      content: contentPool[Math.floor(Math.random() * contentPool.length)],
      rolls: [],
      createdAt: now.toISOString(),
      updatedAt: now.toISOString(),
    };
    rawMessages[targetChat.id].push(msg);
    messages[targetChat.id] = [msg];

    targetChat.lastMessage = msg.content;
    targetChat.lastMessageAt = msg.createdAt;
    targetChat.unreadCount = (targetChat.unreadCount || 0) + 1;
    updates.push({ ...targetChat });
  }

  if (syncCallCount % 7 === 0 && mockChats.length < 30) {
    const available = realUsers.filter(
      (u) =>
        u.id !== 1 &&
        u.id !== 0 &&
        !mockChats.some((c) => c.type === 'private' && c.members.some((m) => m.userId === u.id)),
    );
    if (!available.length) {
      /* no new private chats possible */
    }

    msgIdSeq++;
    const newId = msgIdSeq + 1000;
    const nowISO = now.toISOString();
    const otherUser = available[Math.floor(Math.random() * available.length)];
    const chat: Chat = {
      id: newId,
      type: 'private',
      name: userName(otherUser),
      unreadCount: 1,
      lastReadMessageId: null,
      lastMessage: 'Привет! Давно не виделись',
      lastMessageAt: nowISO,
      members: [member(1, 'member'), member(otherUser.id, 'member')],
    };
    mockChats.push(chat);
    newChats.push({ ...chat });

    msgIdSeq++;
    rawMessages[newId] = [
      {
        id: msgIdSeq,
        chatId: newId,
        userId: otherUser.id,
        username: userName(otherUser),
        content: 'Привет! Давно не виделись',
        rolls: [],
        createdAt: nowISO,
        updatedAt: nowISO,
      },
    ];
  }

  return { now: now.toISOString(), chats: updates, newChats, messages };
}
