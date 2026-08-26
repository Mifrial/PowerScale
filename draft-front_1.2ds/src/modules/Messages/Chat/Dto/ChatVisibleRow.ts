import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatFoldTone } from '@/modules/Messages/Chat/Enum/ChatFoldTone';
import type { ChatFoldVariant } from '@/modules/Messages/Chat/Enum/ChatFoldVariant';

export type ChatVisibleRow =
  | { type: 'message'; key: string; message: ChatMessage; unread: boolean }
  | {
      type: 'chrome';
      key: string;
      foldId: string;
      kind: string;
      summary: string;
      expanded: boolean;
      level: number;
      tone: ChatFoldTone;
      variant: ChatFoldVariant;
      unread: boolean;
    }
  | {
      type: 'panel';
      key: string;
      foldId: string;
      kind: string;
      summary: string;
      expanded: boolean;
      level: number;
      messages: ChatMessage[];
      unread: boolean;
      unreadMessageId: number | null;
    };
