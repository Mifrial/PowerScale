import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatMessagePage } from '@/modules/Messages/Chat/Dto/ChatMessagePage';
import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';

/**
 * Кладёт и читает kind/thread через opaque attachment, пока PHP не принимает эти поля.
 */
export class ChatSystemAttachmentService {
  /**
   * Фиксирует код типа вложения.
   *
   * @param attachmentType Код типа.
   */
  constructor(private readonly attachmentType: string) {}

  attachmentsWithSystem(
    attachments: ChatAttachment[],
    kind: ChatMessage['kind'],
    thread?: ChatThreadRef,
  ): ChatAttachment[] {
    const withoutSystem = attachments.filter((attachment) => attachment.type !== this.attachmentType);
    const payload: { kind: ChatMessage['kind']; thread?: ChatThreadRef } = { kind };
    if (thread !== undefined) payload.thread = thread;

    return [...withoutSystem, { type: this.attachmentType, payload }];
  }

  messageOf(message: ChatMessage): ChatMessage {
    const found = message.attachments.find((attachment) => attachment.type === this.attachmentType);
    const meta = this.metaFromPayload(found?.payload);
    if (meta === null) return message;

    return {
      ...message,
      kind: meta.kind,
      ...(meta.thread !== undefined ? { thread: meta.thread } : {}),
    };
  }

  pageOf(page: ChatMessagePage): ChatMessagePage {
    return { items: page.items.map((item) => this.messageOf(item)), total: page.total };
  }

  private metaFromPayload(payload: unknown): { kind: ChatMessage['kind']; thread?: ChatThreadRef } | null {
    if (payload === null || typeof payload !== 'object') return null;
    const record = payload as Record<string, unknown>;
    if (record.kind !== 'default' && record.kind !== 'highlighted') return null;
    const thread = this.threadFrom(record.thread);

    return thread === undefined ? { kind: record.kind } : { kind: record.kind, thread };
  }

  private threadFrom(value: unknown): ChatThreadRef | undefined {
    if (value === null || typeof value !== 'object') return undefined;
    const record = value as Record<string, unknown>;
    if (typeof record.id !== 'string' || record.id === '' || typeof record.kind !== 'string' || record.kind === '') {
      return undefined;
    }
    if (typeof record.parentId === 'string' && record.parentId !== '') {
      return { id: record.id, parentId: record.parentId, kind: record.kind };
    }

    return { id: record.id, kind: record.kind };
  }
}
