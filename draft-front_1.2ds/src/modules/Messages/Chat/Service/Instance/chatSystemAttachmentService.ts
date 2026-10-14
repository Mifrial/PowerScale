import { CHAT_SYSTEM_ATTACHMENT_TYPE } from '@/modules/Messages/Chat/Constant/Chat/CHAT_SYSTEM_ATTACHMENT_TYPE';
import { ChatSystemAttachmentService } from '@/modules/Messages/Chat/Service/ChatSystemAttachmentService';

export const chatSystemAttachmentService = new ChatSystemAttachmentService(CHAT_SYSTEM_ATTACHMENT_TYPE);
