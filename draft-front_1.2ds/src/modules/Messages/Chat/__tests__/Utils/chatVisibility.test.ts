import { describe, expect, it } from 'vitest';
import { isMessageVisible } from '@/modules/Messages/Chat/Utils/chatVisibility';
import { CHAT_PERMISSION_SEE_ALL } from '@/modules/Messages/Chat/Constant/Chat/CHAT_PERMISSION_SEE_ALL';
import type { ChatRole } from '@/modules/Messages/Chat/Dto/ChatRole';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';

const ROLES: ChatRole[] = [
  { code: 'gm', label: 'Ведущий', permissions: [CHAT_PERMISSION_SEE_ALL] },
  { code: 'player', label: 'Игрок', permissions: [] },
];

const MEMBERS = [
  { userId: 1, status: 'member', role: 'gm', joinedAt: '' },
  { userId: 2, status: 'member', role: 'player', joinedAt: '' },
];

function message(userId: number, visibility?: ChatMessageVisibility): ChatMessage {
  return {
    id: 1,
    chatId: 1,
    userId,
    username: 'X',
    content: 'текст',
    attachments: [],
    createdAt: '',
    updatedAt: '',
    visibility,
  };
}

describe('isMessageVisible', () => {
  it('без видимости сообщение видно всем', () => {
    expect(isMessageVisible(message(3), { members: MEMBERS }, 2, ROLES)).toBe(true);
    expect(isMessageVisible(message(3), { members: MEMBERS }, 99, ROLES)).toBe(true);
  });

  it('отправитель всегда видит своё сообщение', () => {
    const restricted = message(1, { all: false, forRole: 'player' });
    expect(isMessageVisible(restricted, { members: MEMBERS }, 1, ROLES)).toBe(true);
  });

  it('forRole виден носителю роли, игроку — нет', () => {
    const gmOnly = message(3, { all: false, forRole: 'gm' });
    expect(isMessageVisible(gmOnly, { members: MEMBERS }, 1, ROLES)).toBe(true);
    expect(isMessageVisible(gmOnly, { members: MEMBERS }, 2, ROLES)).toBe(false);
    expect(isMessageVisible(gmOnly, { members: MEMBERS }, 99, ROLES)).toBe(false);
  });

  it('forUsers: видно перечисленным пользователям и отправителю', () => {
    const targeted = message(4, { all: false, forUsers: [2] });
    expect(isMessageVisible(targeted, { members: MEMBERS }, 2, ROLES)).toBe(true);
    expect(isMessageVisible(targeted, { members: MEMBERS }, 3, ROLES)).toBe(false);
    expect(isMessageVisible(targeted, { members: MEMBERS }, 4, ROLES)).toBe(true);
  });

  it('роль с chat.see_all (ГМ) видит всё, включая скрытое', () => {
    const targeted = message(3, { all: false, forUsers: [99] });
    expect(isMessageVisible(targeted, { members: MEMBERS }, 1, ROLES)).toBe(true);
    const playerOnly = message(3, { all: false, forRole: 'player' });
    expect(isMessageVisible(playerOnly, { members: MEMBERS }, 1, ROLES)).toBe(true);
  });

  it('без ролей (не доменный чат) ограничение скрывает сообщение', () => {
    const restricted = message(3, { all: false, forRole: 'gm' });
    expect(isMessageVisible(restricted, { members: MEMBERS }, 2, [])).toBe(false);
  });
});
