import { describe, it, expect } from 'vitest';
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
