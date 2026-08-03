import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';

export interface SyncResponse {
  now: string;
  chats: Chat[];
  newChats: Chat[];
  messages: Record<number, ChatMessage[]>;
}
