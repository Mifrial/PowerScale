import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';

/**
 * Готовый «контекст правил» чата: чипы `[[rule:...]]`, источники «Вставить ссылку» и
 * обработка вложений (броски через механики ревизии чата). Собирает донор (Game/Character)
 * по ревизии, к которой относится чат; для хоста — непрозрачный (только свои типы),
 * передаётся в ChatThread/ChatInput как есть.
 */
export interface ChatRulesContext {
  /** Имена правил по коду (для inline-чипов и RuleChip). */
  ruleNames: Record<string, string>;
  /** Источники токенов «Вставить ссылку»: правило из ревизии чата + глобальные прочие. */
  tokenSources: ITokenSource[];
  /** Преобразование вложений перед отправкой (броски через RollEngine). */
  processAttachments: (attachments: ChatAttachment[]) => ChatAttachment[];
  /** Ревизия, из которой построен контекст (для резолва правила в RuleSlider). */
  spaceId?: number | null;
  rulesRevision?: number | null;
}
