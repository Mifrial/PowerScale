import { describe, expect, it } from 'vitest';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatFoldChild, ChatFoldNode } from '@/modules/Messages/Chat/Dto/ChatFold';
import { flattenChatFolds } from '@/modules/Messages/Chat/Utils/flattenChatFolds';

function msg(id: number, content: string, extra: Partial<ChatMessage> = {}): ChatMessage {
  return {
    id,
    chatId: 1,
    userId: 1,
    username: 't',
    content,
    attachments: [],
    createdAt: `2026-08-25T10:00:${String(id).padStart(2, '0')}Z`,
    updatedAt: `2026-08-25T10:00:${String(id).padStart(2, '0')}Z`,
    ...extra,
  };
}

function fold(partial: Partial<ChatFoldNode> & Pick<ChatFoldNode, 'id' | 'summary' | 'children'>): ChatFoldNode {
  return {
    kind: 'turn',
    chrome: 'start',
    tone: 'default',
    variant: 'divider',
    messageIds: [],
    ...partial,
  };
}

describe('flattenChatFolds', () => {
  it('свёрнутая группа — одна строка хрома', () => {
    const forest: ChatFoldChild[] = [
      {
        type: 'fold',
        fold: fold({
          id: 't1',
          summary: 'Ходит Гаррик',
          messageIds: [1, 2],
          children: [
            { type: 'message', message: msg(1, 'a') },
            { type: 'message', message: msg(2, 'b') },
          ],
        }),
      },
    ];
    const rows = flattenChatFolds(forest, () => false, null);
    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({ type: 'chrome', foldId: 't1', expanded: false });
  });

  it('раскрытый ход: хром сверху, дети ниже', () => {
    const forest: ChatFoldChild[] = [
      {
        type: 'fold',
        fold: fold({
          id: 't1',
          summary: 'Ходит Гаррик',
          messageIds: [1],
          children: [{ type: 'message', message: msg(1, 'реплика') }],
        }),
      },
    ];
    const rows = flattenChatFolds(forest, () => true, null);
    expect(rows.map((row) => row.type)).toEqual(['chrome', 'message']);
  });

  it('атака: одна панель, тело внутри при развороте', () => {
    const forest: ChatFoldChild[] = [
      {
        type: 'fold',
        fold: fold({
          id: 'a1',
          kind: 'attack',
          summary: 'попадает',
          chrome: 'end',
          variant: 'block',
          messageIds: [1, 2],
          children: [
            { type: 'message', message: msg(1, 'удар') },
            { type: 'message', message: msg(2, 'результат') },
          ],
        }),
      },
    ];
    const open = flattenChatFolds(forest, () => true, null);
    expect(open).toHaveLength(1);
    expect(open[0]).toMatchObject({ type: 'panel', foldId: 'a1', expanded: true });
    if (open[0]?.type === 'panel') expect(open[0].messages.map((message) => message.id)).toEqual([1, 2]);
    const closed = flattenChatFolds(forest, () => false, 2);
    expect(closed[0]).toMatchObject({ type: 'panel', foldId: 'a1', expanded: false, messages: [], unread: true });
  });

  it('непрочитанное внутри свёртки помечает хром', () => {
    const forest: ChatFoldChild[] = [
      {
        type: 'fold',
        fold: fold({
          id: 't1',
          summary: 'Ход',
          messageIds: [5, 6],
          children: [
            { type: 'message', message: msg(5, 'a') },
            { type: 'message', message: msg(6, 'b') },
          ],
        }),
      },
    ];
    const rows = flattenChatFolds(forest, () => false, 6);
    expect(rows[0]).toMatchObject({ type: 'chrome', unread: true });
  });
});
