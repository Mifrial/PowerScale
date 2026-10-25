import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatMessagePage } from '@/modules/Messages/Chat/Dto/ChatMessagePage';
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';
import { chatSpeakerAttachmentService } from '@/modules/Messages/Chat/Service/Instance/chatSpeakerAttachmentService';
import { chatSystemAttachmentService } from '@/modules/Messages/Chat/Service/Instance/chatSystemAttachmentService';

/**
 * Real Chat: action и страница; SSE не через runAction.
 */
export class ChatApi implements IChatApi {
  /**
   * Сохраняет Engine для HTTP action.
   *
   * @param engine Фасад HTTP.
   */
  constructor(private readonly engine: Engine) {}

  async getChats(): Promise<Chat[]> {
    const res = await this.engine.runAction<Chat[]>('chat.getChats');
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to load chats');

    return res.data;
  }

  async findMessagePage(chatId: number, limit: number, offset: number): Promise<ChatMessagePage> {
    const res = await this.engine.runAction<ChatMessagePage>('chat.findMessagePage', { chatId, limit, offset });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to load messages');

    return chatSystemAttachmentService.pageOf(chatSpeakerAttachmentService.pageOf(res.data));
  }

  async getMessages(chatId: number, limit: number, offset: number): Promise<ChatMessage[]> {
    return (await this.findMessagePage(chatId, limit, offset)).items;
  }

  async getMessagesBefore(_chatId: number, _beforeId: number, _limit: number): Promise<ChatMessage[]> {
    throw new Error('getMessagesBefore is not an action');
  }

  async getTotalMessageCount(chatId: number): Promise<number> {
    return (await this.findMessagePage(chatId, 1, 0)).total;
  }

  async sendMessage(
    chatId: number,
    content: string,
    attachments: ChatAttachment[],
    speaker?: ChatSpeaker,
    visibility?: ChatMessageVisibility,
    _thread?: ChatThreadRef,
  ): Promise<ChatMessage> {
    const withSpeaker = chatSpeakerAttachmentService.attachmentsWithSpeaker(attachments, speaker);
    const encoded = _thread
      ? chatSystemAttachmentService.attachmentsWithSystem(withSpeaker, undefined, _thread)
      : withSpeaker;
    const payload: Record<string, unknown> = { chatId, content, attachments: encoded };
    if (visibility !== undefined) payload.visibility = visibility;
    const res = await this.engine.runAction<ChatMessage>('chat.sendMessage', payload);
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to send message');

    return chatSystemAttachmentService.messageOf(
      chatSpeakerAttachmentService.messageOf({ ...res.data, speaker: res.data.speaker ?? speaker }),
    );
  }

  async updateMessageVisibility(
    chatId: number,
    messageId: number,
    visibility?: ChatMessageVisibility,
  ): Promise<ChatMessage> {
    const payload: Record<string, unknown> = { chatId, messageId };
    if (visibility !== undefined) payload.visibility = visibility;
    const res = await this.engine.runAction<ChatMessage>('chat.updateMessageVisibility', payload);
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to update visibility');

    return res.data;
  }

  async sendSystemMessage(
    chatId: number,
    content: string,
    kind: ChatMessage['kind'] = 'default',
    thread?: ChatThreadRef,
  ): Promise<ChatMessage> {
    const message = await this.sendMessage(
      chatId,
      content,
      chatSystemAttachmentService.attachmentsWithSystem([], kind, thread),
    );
    const attachments = chatSystemAttachmentService.attachmentsWithSystem(message.attachments, kind, thread);

    return chatSystemAttachmentService.messageOf({ ...message, attachments });
  }

  async markChatRead(chatId: number): Promise<void> {
    const res = await this.engine.runAction('chat.markChatRead', { chatId });
    if (!res.success) throw new Error(res.error?.message ?? 'Failed to mark chat read');
  }

  async addPrivate(userId: number): Promise<Chat> {
    const res = await this.engine.runAction<Chat>('chat.addPrivate', { userId });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to create private chat');

    return res.data;
  }

  async addGroup(name: string, memberIds: number[] = []): Promise<Chat> {
    const res = await this.engine.runAction<Chat>('chat.addGroup', { name, memberIds });
    if (!res.success || !res.data) throw new Error(res.error?.message ?? 'Failed to create group chat');

    return res.data;
  }

  async sync(_since: number): Promise<SyncResponse> {
    throw new Error('chat.sync is not an action');
  }
}
