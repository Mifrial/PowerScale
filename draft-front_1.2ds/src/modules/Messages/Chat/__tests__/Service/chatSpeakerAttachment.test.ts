import { describe, expect, it } from 'vitest';
import { ChatSpeakerAttachmentService } from '@/modules/Messages/Chat/Service/ChatSpeakerAttachmentService';

const service = new ChatSpeakerAttachmentService('chat_speaker');

describe('ChatSpeakerAttachmentService', () => {
  it('кладёт speaker во вложение и читает обратно', () => {
    const attachments = service.attachmentsWithSpeaker([{ type: 'roll', payload: { n: 1 } }], { kind: 'gm' });
    expect(attachments).toEqual([
      { type: 'roll', payload: { n: 1 } },
      { type: 'chat_speaker', payload: { kind: 'gm' } },
    ]);
    expect(service.speakerOf(attachments)).toEqual({ kind: 'gm' });
  });

  it('messageOf поднимает speaker из вложения', () => {
    const message = service.messageOf({
      id: 1,
      chatId: 1,
      userId: 2,
      username: 'mifrial',
      content: 'hi',
      attachments: [
        {
          type: 'chat_speaker',
          payload: { kind: 'character', characterId: 9, characterName: 'Воюн' },
        },
      ],
      createdAt: 0,
      updatedAt: 0,
    });
    expect(message.speaker).toEqual({ kind: 'character', characterId: 9, characterName: 'Воюн' });
  });
});
