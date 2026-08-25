/** Группа сообщений в ленте (свёртка). `kind` непрозрачен для хоста Chat. */
export interface ChatThreadRef {
  id: string;
  parentId?: string;
  kind: string;
}
