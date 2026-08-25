import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';

export type ChatFoldTone = 'default' | 'highlighted';
export type ChatFoldChrome = 'start' | 'end';
export type ChatFoldVariant = 'divider' | 'block';

export interface ChatFoldNode {
  id: string;
  kind: string;
  summary: string;
  chrome: ChatFoldChrome;
  tone: ChatFoldTone;
  variant: ChatFoldVariant;
  children: ChatFoldChild[];
  messageIds: number[];
}

export type ChatFoldChild = { type: 'message'; message: ChatMessage } | { type: 'fold'; fold: ChatFoldNode };

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
