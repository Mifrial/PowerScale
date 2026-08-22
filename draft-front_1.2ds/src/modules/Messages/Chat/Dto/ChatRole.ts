/**
 * Роль в чате (значения задаёт домен: Game для игровых чатов — 'gm'/'player').
 * Chat роли не интерпретирует — это данные; оценка прав — generic (см. chat.see_all).
 */
export interface ChatRole {
  code: string;
  label: string;
  /** Права роли (например `chat.see_all` — видит все сообщения, включая скрытые). */
  permissions: string[];
}
