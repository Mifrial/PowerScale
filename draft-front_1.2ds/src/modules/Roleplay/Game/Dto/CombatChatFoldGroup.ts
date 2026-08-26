import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';

export interface CombatChatFoldGroup {
  id: string;
  kind: string;
  parentId?: string;
  messages: ChatMessage[];
}
