import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';

export interface ChatState {
  messages: ChatMessage[];
  loadedCount: number;
  hasMore: boolean;
  total: number;
  initialized: boolean;
  loading: boolean;
  loadingOlder: boolean;
  olderError: string;
}
