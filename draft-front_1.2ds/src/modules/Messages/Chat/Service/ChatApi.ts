import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';

export class ChatApi implements IChatApi {
  constructor(private readonly engine: Engine) {}

  async getChats(): Promise<Chat[]> {
    const res = await this.engine.runAction<Chat[]>('chat.getChats');
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to load chats');

    return res.data;
  }

  async getMessages(chatId: number, limit: number, offset: number): Promise<ChatMessage[]> {
    const res = await this.engine.runAction<ChatMessage[]>('chat.getMessages', { chatId, limit, offset });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to load messages');

    return res.data;
  }

  async getMessagesBefore(chatId: number, beforeId: number, limit: number): Promise<ChatMessage[]> {
    const res = await this.engine.runAction<ChatMessage[]>('chat.getMessages', { chatId, beforeId, limit });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to load messages');

    return res.data;
  }

  async getTotalMessageCount(chatId: number): Promise<number> {
    const res = await this.engine.runAction<number>('chat.getTotalMessageCount', { chatId });
    if (!res.success || res.data === null) throw new Error(res.error?.message ?? 'Failed to get message count');

    return res.data;
  }

  async sendMessage(
    chatId: number,
    content: string,
    attachments: ChatAttachment[],
    speaker?: ChatSpeaker,
    visibility?: ChatMessageVisibility,
  ): Promise<ChatMessage> {
    const res = await this.engine.runAction<ChatMessage>('chat.sendMessage', {
      chatId,
      content,
      attachments,
      speaker,
      visibility,
    });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to send message');

    return res.data;
  }

  async updateMessageVisibility(
    chatId: number,
    messageId: number,
    visibility?: ChatMessageVisibility,
  ): Promise<ChatMessage> {
    const res = await this.engine.runAction<ChatMessage>('chat.updateMessageVisibility', {
      chatId,
      messageId,
      visibility,
    });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to update message visibility');

    return res.data;
  }

  async sendSystemMessage(chatId: number, content: string, kind?: ChatMessage['kind']): Promise<ChatMessage> {
    const res = await this.engine.runAction<ChatMessage>('chat.sendSystemMessage', { chatId, content, kind });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to send system message');

    return res.data;
  }

  async markChatRead(chatId: number): Promise<void> {
    await this.engine.runAction('chat.markChatRead', { chatId });
  }

  async sync(since: string): Promise<SyncResponse> {
    const res = await this.engine.runAction<SyncResponse>('chat.sync', { since });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Sync failed');

    return res.data;
  }
}
