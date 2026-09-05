import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { ChatSyncHealth } from '@/modules/Messages/Chat/Dto/ChatSyncHealth';
import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';

/** Параметры poll или SSE-канала чата. */
export interface ChatSyncConfig {
  onSync: (data: SyncResponse) => void;
  onStatus?: (health: ChatSyncHealth) => void;
  mode?: 'poll' | 'sse';
  pollInterval?: number;
  initialBackoffMs?: number;
  maxBackoffMs?: number;
  engine?: Engine;
  getSyncApi?: () => IChatApi;
}
