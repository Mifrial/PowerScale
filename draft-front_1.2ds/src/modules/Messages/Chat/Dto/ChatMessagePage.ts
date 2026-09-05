import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';

/** Страница ленты: items + total, как chat.findMessagePage. */
export interface ChatMessagePage {
  items: ChatMessage[];
  total: number;
}
