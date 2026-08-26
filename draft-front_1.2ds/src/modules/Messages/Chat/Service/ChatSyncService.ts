import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { ChatSyncConfig } from '@/modules/Messages/Chat/Dto/ChatSyncConfig';

export class ChatSyncService {
  private source: EventSource | null = null;
  private timer: ReturnType<typeof setInterval> | null = null;
  private lastSync = '';

  constructor(private readonly config: ChatSyncConfig) {}

  get lastSyncTimestamp(): string {
    return this.lastSync;
  }

  connect(since: string): void {
    this.lastSync = since;
    if (this.config.mode === 'sse') {
      this.startSSE();
    } else {
      this.startPolling();
    }
  }

  disconnect(): void {
    this.source?.close();
    this.source = null;
    if (this.timer !== null) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  private applyFrame(payload: unknown): void {
    if (!ChatSyncService.isSyncResponse(payload)) return;
    this.lastSync = payload.now;
    this.config.onSync(payload);
  }

  private static isSyncResponse(value: unknown): value is SyncResponse {
    if (typeof value !== 'object' || value === null) return false;
    const row = value as Record<string, unknown>;
    if (typeof row.now !== 'string' || !Array.isArray(row.chats) || !Array.isArray(row.newChats)) return false;
    if (typeof row.messages !== 'object' || row.messages === null || Array.isArray(row.messages)) return false;

    return Object.values(row.messages).every((entry) => Array.isArray(entry));
  }

  private startPolling(): void {
    const sync = async () => {
      const syncApi = this.config.getSyncApi;
      if (!syncApi) return;
      try {
        const res = await syncApi().sync(this.lastSync);
        this.applyFrame(res);
      } catch {
        // Фоновая синхронизация: ошибки пропускаем, следующий тик повторит запрос.
      }
    };
    sync();
    this.timer = setInterval(sync, this.config.pollInterval ?? 5000);
  }

  private startSSE(): void {
    const url = `${this.config.baseUrl ?? ''}/api/chat/sync?since=${encodeURIComponent(this.lastSync)}`;
    const source = new EventSource(url, { withCredentials: true });
    this.source = source;
    source.addEventListener('sync', (e: MessageEvent) => {
      try {
        this.applyFrame(JSON.parse(e.data));
      } catch {
        // Событие-мусор с сервера не должно ронять синхронизацию.
      }
    });
    source.onerror = () => {
      if (this.source !== source) return;
      source.close();
      this.source = null;
      this.startSSE();
    };
  }
}
