import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';

/**
 * Контекст хоста для заметок. Для Notes он opaque: модуль передаёт его в редактор/просмотр,
 * а ключи толкуют только зарегистрированные inline-рендереры доменных модулей.
 */
export interface NoteHostContext {
  /** Opaque inline-контекст: подписи токенов, игра и роль ссылок; Notes его не читает. */
  inlineContext: Record<string, unknown>;
  /** Источники «Вставить ссылку», доступные в контексте этого хоста. */
  tokenSources: ITokenSource[];
}
