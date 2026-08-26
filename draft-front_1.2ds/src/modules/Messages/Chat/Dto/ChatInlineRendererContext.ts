/** Data-срез хоста для зарегистрированных inline-рендереров: подписи токенов и ключи среза. Без домена Rule. */
export interface ChatInlineRendererContext {
  /** Подписи токенов по коду (`[[type:code]]`). */
  tokenLabels: Record<string, string>;
  spaceId?: number | null;
  rulesRevision?: number | null;
}
