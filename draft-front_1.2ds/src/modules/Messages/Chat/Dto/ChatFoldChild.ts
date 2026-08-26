import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatFoldNode } from '@/modules/Messages/Chat/Dto/ChatFoldNode';

export type ChatFoldChild = { type: 'message'; message: ChatMessage } | { type: 'fold'; fold: ChatFoldNode };
