import type { Chat, ChatMessage, DiceRollSpec, SyncResponse } from './types'

export interface IChatApi {
  getChats(): Promise<Chat[]>
  getMessages(chatId: number, limit: number, offset: number): Promise<ChatMessage[]>
  getTotalMessageCount(chatId: number): Promise<number>
  sendMessage(chatId: number, content: string, rolls: DiceRollSpec[]): Promise<ChatMessage>
  markChatRead(chatId: number): Promise<void>
  sync(since: string): Promise<SyncResponse>
}
