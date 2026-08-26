import { computed, ref } from 'vue';
import { resolveChatRules } from '@/modules/Messages/Chat/init';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import { chatInlineRendererContext } from '@/modules/Messages/Chat/Utils/chatInlineRendererContext';

/**
 * Резолв пакета донора для активного чата: data в ref, колбэки ввода — вне reactive state.
 * Сбой — отдельная ошибка (не `chatError`); `null` провайдера — не ошибка.
 */
export function useChatRulesResolution() {
  const inlineContext = ref<ChatInlineRendererContext | null>(null);
  const error = ref<string | null>(null);
  const loading = ref(false);
  const pipelineTick = ref(0);
  let tokenSources: ITokenSource[] | undefined;
  let processAttachments: ((attachments: ChatAttachment[]) => ChatAttachment[]) | undefined;
  let requestId = 0;
  let lastType: string | undefined;
  let lastChatId: number | null = null;

  const sources = computed(() => {
    void pipelineTick.value;

    return tokenSources;
  });

  const process = computed(() => {
    void pipelineTick.value;

    return processAttachments;
  });

  function apply(context: ChatRulesContext | null): void {
    tokenSources = context?.tokenSources;
    processAttachments = context?.processAttachments;
    inlineContext.value = chatInlineRendererContext(context);
    pipelineTick.value++;
  }

  async function resolveFor(type: string | undefined, chatId: number | null): Promise<void> {
    lastType = type;
    lastChatId = chatId;
    const id = ++requestId;
    error.value = null;
    if (type == null || chatId == null) {
      apply(null);

      return;
    }
    loading.value = true;
    try {
      const context = await resolveChatRules(type, chatId);
      if (id !== requestId) return;
      apply(context);
    } catch (caught) {
      if (id !== requestId) return;
      apply(null);
      error.value = caught instanceof Error ? caught.message : 'Не удалось загрузить контекст чата';
    } finally {
      if (id === requestId) loading.value = false;
    }
  }

  function retry(): Promise<void> {
    return resolveFor(lastType, lastChatId);
  }

  return {
    inlineContext,
    error,
    loading,
    tokenSources: sources,
    processAttachments: process,
    resolveFor,
    retry,
  };
}
