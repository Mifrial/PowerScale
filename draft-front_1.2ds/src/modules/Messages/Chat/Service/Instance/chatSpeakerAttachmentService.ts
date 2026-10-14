import { CHAT_SPEAKER_ATTACHMENT_TYPE } from '@/modules/Messages/Chat/Constant/Chat/CHAT_SPEAKER_ATTACHMENT_TYPE';
import { ChatSpeakerAttachmentService } from '@/modules/Messages/Chat/Service/ChatSpeakerAttachmentService';

export const chatSpeakerAttachmentService = new ChatSpeakerAttachmentService(CHAT_SPEAKER_ATTACHMENT_TYPE);
