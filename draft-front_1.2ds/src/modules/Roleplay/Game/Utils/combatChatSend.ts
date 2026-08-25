import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';

export function sendCombatChat(gameId: number) {
  const store = useChatStore();

  return (content: string, attachments: ChatAttachment[], chatId: number, speaker?: ChatSpeaker): Promise<boolean> => {
    const thread = useCombatChatThread(gameId).stamp();

    return store.sendMessage(content, attachments, chatId, speaker, undefined, thread);
  };
}
