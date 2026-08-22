import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';

export function messagePreview(content: string, attachments: ChatAttachment[]): string {
  return content || (attachments.length ? `${attachments.length} вложен.` : '');
}
