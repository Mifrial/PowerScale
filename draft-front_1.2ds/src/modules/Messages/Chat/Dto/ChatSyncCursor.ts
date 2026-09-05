/** Курсор SSE: пара кадра или live (null). */
export interface ChatSyncCursor {
  since: number;
  afterId: number;
}
