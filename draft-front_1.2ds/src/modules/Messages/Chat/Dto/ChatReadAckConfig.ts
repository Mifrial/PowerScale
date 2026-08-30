import type { ChatSyncHealth } from '@/modules/Messages/Chat/Dto/ChatSyncHealth';

export interface ChatReadAckConfig {
  markChatRead: (chatId: number) => Promise<void>;
  onStatus?: (chatId: number, health: ChatSyncHealth) => void;
  initialBackoffMs?: number;
  maxBackoffMs?: number;
}
