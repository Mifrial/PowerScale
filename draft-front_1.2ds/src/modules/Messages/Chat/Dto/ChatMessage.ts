import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatMessageVisibility } from '@/modules/Messages/Chat/Dto/ChatMessageVisibility';
import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';

export interface ChatMessage {
  id: number;
  chatId: number;
  userId: number;
  username: string;
  content: string;
  attachments: ChatAttachment[];
  createdAt: string;
  updatedAt: string;
  /** «От лица кого» (игровой чат): персонаж/НПС/ведущий. Отсутствует в обычных чатах. */
  speaker?: ChatSpeaker;
  /** Системное уведомление (рендерится разделителем, не карточкой). `default` — напр. «Ходит Имя»; `highlighted` — акцентное (цветом primary). */
  kind?: 'default' | 'highlighted';
  /** Видимость сообщения (роли/пользователи); оценка — Utils/chatVisibility. */
  visibility?: ChatMessageVisibility;
  /** Непрозрачная группа свёртки (раунд/ход/атака и т.п.). Хост Chat kind не интерпретирует. */
  thread?: ChatThreadRef;
}
