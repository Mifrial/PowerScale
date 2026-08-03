import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';

export interface IChatApi {
  getChats(): Promise<Chat[]>;
  getMessages(chatId: number, limit: number, offset: number): Promise<ChatMessage[]>;
  getTotalMessageCount(chatId: number): Promise<number>;
  sendMessage(chatId: number, content: string, rolls: DiceRollSpec[]): Promise<ChatMessage>;
  markChatRead(chatId: number): Promise<void>;
  sync(since: string): Promise<SyncResponse>;
}
