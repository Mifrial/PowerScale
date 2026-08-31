import { beforeEach, describe, expect, it, vi } from 'vitest';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerChatRulesProvider } from '@/modules/Messages/Chat/init';
import { useChatRulesResolution } from '@/modules/Messages/Chat/Composables/useChatRulesResolution';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';

const emptyContext = (overrides: Partial<ChatRulesContext> = {}): ChatRulesContext => ({
  tokenLabels: { movement: 'Движение' },
  tokenSources: [],
  processAttachments: (attachments) => attachments,
  spaceId: 1,
  rulesRevision: 5,
  ...overrides,
});

beforeEach(() => {
  resetRegisteredApis();
});

describe('useChatRulesResolution', () => {
  it('кладёт data-срез и не считает null ошибкой', async () => {
    registerChatRulesProvider({
      types: ['res-ok'],
      resolve: async () => emptyContext(),
    });
    const resolution = useChatRulesResolution();

    await resolution.resolveFor('res-ok', 1);

    expect(resolution.error.value).toBeNull();
    expect(resolution.inlineContext.value?.tokenLabels.movement).toBe('Движение');
    expect(resolution.inlineContext.value?.spaceId).toBe(1);
    expect(resolution.tokenSources.value).toEqual([]);
  });

  it('null провайдера — не ошибка', async () => {
    registerChatRulesProvider({ types: ['res-null'], resolve: async () => null });
    const resolution = useChatRulesResolution();

    await resolution.resolveFor('res-null', 1);

    expect(resolution.error.value).toBeNull();
    expect(resolution.inlineContext.value).toBeNull();
  });

  it('исключение — отдельная ошибка, retry повторяет запрос', async () => {
    const resolve = vi.fn().mockRejectedValueOnce(new Error('сеть')).mockResolvedValueOnce(emptyContext());
    registerChatRulesProvider({ types: ['res-err'], resolve });
    const resolution = useChatRulesResolution();

    await resolution.resolveFor('res-err', 7);

    expect(resolution.error.value).toBe('сеть');
    expect(resolution.inlineContext.value).toBeNull();

    await resolution.retry();

    expect(resolve).toHaveBeenCalledTimes(2);
    expect(resolution.error.value).toBeNull();
    expect(resolution.inlineContext.value?.tokenLabels.movement).toBe('Движение');
  });
});
