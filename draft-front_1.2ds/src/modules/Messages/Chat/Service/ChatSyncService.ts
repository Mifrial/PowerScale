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

  private startPolling(): void {
    const sync = async () => {
      const syncApi = this.config.getSyncApi;
      if (!syncApi) return;
      try {
        const res = await syncApi().sync(this.lastSync);
        this.lastSync = res.now;
        this.config.onSync(res);
      } catch {
        // Фоновая синхронизация: ошибки пропускаем, следующий тик повторит запрос.
      }
    };
    sync();
    this.timer = setInterval(sync, this.config.pollInterval ?? 5000);
  }

  private startSSE(): void {
    const url = `${this.config.baseUrl ?? ''}/api/chat/sync?since=${encodeURIComponent(this.lastSync)}`;
    this.source = new EventSource(url, { withCredentials: true });
    this.source.addEventListener('sync', (e: MessageEvent) => {
      try {
        const data = JSON.parse(e.data) as SyncResponse;
        this.lastSync = data.now;
        this.config.onSync(data);
      } catch {
        // Событие-мусор с сервера не должно ронять синхронизацию.
      }
    });
  }
}
