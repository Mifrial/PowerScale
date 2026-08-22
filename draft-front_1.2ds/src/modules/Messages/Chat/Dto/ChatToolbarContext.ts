import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';

export interface ChatToolbarContext {
  attachments: ChatAttachment[];
  addAttachment(attachment: ChatAttachment): void;
  removeAttachment(index: number): void;
  send(text: string, attachments: ChatAttachment[]): void;
  disabled: boolean;
}
