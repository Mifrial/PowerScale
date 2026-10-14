import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatMessagePage } from '@/modules/Messages/Chat/Dto/ChatMessagePage';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';

/**
 * Кладёт и читает speaker через opaque attachment, пока PHP не принимает поле speaker.
 */
export class ChatSpeakerAttachmentService {
  /**
   * Фиксирует код типа вложения.
   *
   * @param attachmentType Код типа.
   */
  constructor(private readonly attachmentType: string) {}

  attachmentsWithSpeaker(attachments: ChatAttachment[], speaker?: ChatSpeaker): ChatAttachment[] {
    const withoutSpeaker = attachments.filter((attachment) => attachment.type !== this.attachmentType);
    if (!speaker) return withoutSpeaker;

    return [...withoutSpeaker, { type: this.attachmentType, payload: speaker }];
  }

  speakerOf(attachments: ChatAttachment[], fallback?: ChatSpeaker): ChatSpeaker | undefined {
    if (fallback) return fallback;
    const found = attachments.find((attachment) => attachment.type === this.attachmentType);

    return this.speakerFromPayload(found?.payload) ?? undefined;
  }

  messageOf(message: ChatMessage): ChatMessage {
    const speaker = this.speakerOf(message.attachments, message.speaker);
    if (speaker === message.speaker) return message;

    return { ...message, speaker };
  }

  pageOf(page: ChatMessagePage): ChatMessagePage {
    return { items: page.items.map((item) => this.messageOf(item)), total: page.total };
  }

  private speakerFromPayload(payload: unknown): ChatSpeaker | null {
    if (payload === null || typeof payload !== 'object') return null;
    const record = payload as Record<string, unknown>;
    if (record.kind === 'gm') return { kind: 'gm' };
    if (
      record.kind === 'character' &&
      typeof record.characterId === 'number' &&
      typeof record.characterName === 'string'
    ) {
      return { kind: 'character', characterId: record.characterId, characterName: record.characterName };
    }
    if (record.kind === 'npc' && typeof record.npcId === 'number' && typeof record.npcName === 'string') {
      return { kind: 'npc', npcId: record.npcId, npcName: record.npcName };
    }

    return null;
  }
}
