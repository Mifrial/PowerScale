import type { ChatType } from '@/modules/Messages/Chat/Enum/ChatType';
import type { ChatVisibility } from '@/modules/Messages/Chat/Enum/ChatVisibility';
import type { MemberInfo } from '@/modules/Messages/Chat/Dto/MemberInfo';

export interface Chat {
  id: number;
  type: ChatType;
  name: string;
  unreadCount: number;
  lastReadMessageId: number | null;
  lastMessage?: string;
  lastMessageAt: string;
  members: MemberInfo[];
  visibility?: ChatVisibility;
}
