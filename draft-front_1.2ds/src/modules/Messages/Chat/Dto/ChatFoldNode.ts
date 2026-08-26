import type { ChatFoldChild } from '@/modules/Messages/Chat/Dto/ChatFoldChild';
import type { ChatFoldChrome } from '@/modules/Messages/Chat/Enum/ChatFoldChrome';
import type { ChatFoldTone } from '@/modules/Messages/Chat/Enum/ChatFoldTone';
import type { ChatFoldVariant } from '@/modules/Messages/Chat/Enum/ChatFoldVariant';

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
