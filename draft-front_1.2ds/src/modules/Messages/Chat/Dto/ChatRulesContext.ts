import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';

/**
 * Пакет донора хосту чата: data-срез для чипов плюс колбэки ввода (пикер токенов, вложения).
 * В reactive state кладётся только `ChatInlineRendererContext`; функции — проп/замыкание.
 */
export interface ChatRulesContext extends ChatInlineRendererContext {
  /** Источники «Вставить ссылку»: токены среза чата + глобальные прочие. */
  tokenSources: ITokenSource[];
  /** Преобразование вложений перед отправкой (донор, напр. броски). */
  processAttachments: (attachments: ChatAttachment[]) => ChatAttachment[];
}
