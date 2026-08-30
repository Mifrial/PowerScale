import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { ChatSyncHealth } from '@/modules/Messages/Chat/Dto/ChatSyncHealth';
import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi';

export interface ChatSyncConfig {
  onSync: (data: SyncResponse) => void;
  onStatus?: (health: ChatSyncHealth) => void;
  /**
   * 'poll' (по умолчанию) — временный транспорт, пока нет SSE-бэкенда (mock-режим).
   * 'sse' — целевой протокол реального бэка (ТР §9): один EventSource /api/chat/sync?since=,
   *        heartbeat 60 c, at-least-once, авто-reconnect. Выбирается конфигом при появлении бэка.
   */
  mode?: 'poll' | 'sse';
  pollInterval?: number;
  initialBackoffMs?: number;
  maxBackoffMs?: number;
  baseUrl?: string;
  getSyncApi?: () => IChatApi;
}
