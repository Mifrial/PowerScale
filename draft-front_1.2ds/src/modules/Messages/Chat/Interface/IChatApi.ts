import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';

export interface IChatApi {
  getChats(): Promise<Chat[]>;
  getMessages(chatId: number, limit: number, offset: number): Promise<ChatMessage[]>;
  getMessagesBefore(chatId: number, beforeId: number, limit: number): Promise<ChatMessage[]>;
  getTotalMessageCount(chatId: number): Promise<number>;
  sendMessage(
    chatId: number,
    content: string,
    attachments: ChatAttachment[],
    speaker?: ChatSpeaker,
    visibility?: ChatMessageVisibility,
    thread?: ChatThreadRef,
  ): Promise<ChatMessage>;
  /** Изменение видимости уже отправленного сообщения (только отправитель; undefined — всем). */
  updateMessageVisibility(chatId: number, messageId: number, visibility?: ChatMessageVisibility): Promise<ChatMessage>;
  /** Системное уведомление (рендерится разделителем, не карточкой): напр. «Ходит Имя» (`kind` — `default`), акцентные — «Новый раунд: N» (`kind` — `highlighted`). */
  sendSystemMessage(
    chatId: number,
    content: string,
    kind?: ChatMessage['kind'],
    thread?: ChatThreadRef,
  ): Promise<ChatMessage>;
  markChatRead(chatId: number): Promise<void>;
  sync(since: string): Promise<SyncResponse>;
}
