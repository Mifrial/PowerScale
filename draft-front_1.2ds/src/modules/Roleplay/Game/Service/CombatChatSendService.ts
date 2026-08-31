import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import { useChatChannel } from '@/modules/Messages/Chat/init';
import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';

export class CombatChatSendService {
  sendCombatChat(gameId: number) {
    const store = useChatChannel();

    return (
      content: string,
      attachments: ChatAttachment[],
      chatId: number,
      speaker?: ChatSpeaker,
    ): Promise<boolean> => {
      const thread = useCombatChatThread(gameId).stamp();

      return store.sendMessage(content, attachments, chatId, speaker, undefined, thread);
    };
  }
}
