import { describe, it, expect, vi } from 'vitest';
import { ChatApi } from '@/modules/Messages/Chat/Service/ChatApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';

describe('ChatApi.markChatRead', () => {
  it('бросает при success: false', async () => {
    const engine = {
      runAction: async () => ({ success: false, data: null, error: { code: 'fail', message: 'нет' } }),
    };
    const api = new ChatApi(engine as unknown as Engine);

    await expect(api.markChatRead(1)).rejects.toThrow('нет');
  });
});

describe('ChatApi.sendMessage', () => {
  it('кладёт в JSON только chatId, content, attachments', async () => {
    const runAction = vi.fn().mockResolvedValue({
      success: true,
      data: {
        id: 1,
        chatId: 1,
        userId: 1,
        username: 'U',
        content: 'hi',
        attachments: [],
        createdAt: 0,
        updatedAt: 0,
      },
    });
    const api = new ChatApi({ runAction } as unknown as Engine);
    await api.sendMessage(1, 'hi', [], { kind: 'gm' }, { all: false }, { id: 't', kind: 'turn' });
    expect(runAction).toHaveBeenCalledWith('chat.sendMessage', {
      chatId: 1,
      content: 'hi',
      attachments: [],
      visibility: { all: false },
    });
  });

  it('не кладёт visibility, если undefined', async () => {
    const runAction = vi.fn().mockResolvedValue({
      success: true,
      data: {
        id: 1,
        chatId: 1,
        userId: 1,
        username: 'U',
        content: 'hi',
        attachments: [],
        createdAt: 0,
        updatedAt: 0,
      },
    });
    const api = new ChatApi({ runAction } as unknown as Engine);
    await api.sendMessage(1, 'hi', []);
    expect(runAction).toHaveBeenCalledWith('chat.sendMessage', { chatId: 1, content: 'hi', attachments: [] });
  });
});

describe('ChatApi.updateMessageVisibility', () => {
  it('зовёт chat.updateMessageVisibility', async () => {
    const runAction = vi.fn().mockResolvedValue({
      success: true,
      data: {
        id: 2,
        chatId: 1,
        userId: 1,
        username: 'U',
        content: 'hi',
        attachments: [],
        createdAt: 0,
        updatedAt: 0,
        visibility: { all: true },
      },
    });
    const api = new ChatApi({ runAction } as unknown as Engine);
    await api.updateMessageVisibility(1, 2);
    expect(runAction).toHaveBeenCalledWith('chat.updateMessageVisibility', { chatId: 1, messageId: 2 });
  });
});
