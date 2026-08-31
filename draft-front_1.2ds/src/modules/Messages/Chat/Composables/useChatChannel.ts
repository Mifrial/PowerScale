import { useChatStore } from '@/modules/Messages/Chat/Store/chat';

export function useChatChannel() {
  const store = useChatStore();

  return {
    sendMessage: store.sendMessage,
    postSystemMessage: store.postSystemMessage,
    messagesOf: store.messagesOf,
  };
}
