/**
 * Видимость сообщения: не задана — всем. `all: false` вместе с `forRole`/`forUsers`
 * ограничивает аудиторию (union). Оценка — `Utils/chatVisibility` (отправитель всегда
 * видит своё; роль с правом `chat.see_all` видит всё, включая скрытое).
 */
export interface ChatMessageVisibility {
  all?: boolean;
  /** Роли (коды ролей чата), которым видно. */
  forRole?: string | string[];
  /** Конкретные пользователи, которым видно. */
  forUsers?: number[];
}
