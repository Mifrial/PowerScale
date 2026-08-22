import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';

export interface ParsedCommand {
  content: string;
  attachments: ChatAttachment[];
}
