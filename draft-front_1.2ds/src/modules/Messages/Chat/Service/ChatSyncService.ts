import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { ChatSyncConfig } from '@/modules/Messages/Chat/Dto/ChatSyncConfig';
import type { ChatSyncHealth } from '@/modules/Messages/Chat/Dto/ChatSyncHealth';
import { SYNC_INITIAL_BACKOFF_MS } from '@/modules/Messages/Chat/Constant/Chat/SYNC_INITIAL_BACKOFF_MS';
import { SYNC_MAX_BACKOFF_MS } from '@/modules/Messages/Chat/Constant/Chat/SYNC_MAX_BACKOFF_MS';

export class ChatSyncService {
  private source: EventSource | null = null;
  private timer: ReturnType<typeof setTimeout> | null = null;
  private lastSync = '';
  private generation = 0;
  private connected = false;
  private pollInFlight = false;
  private nextBackoffMs: number;
  private readonly initialBackoffMs: number;
  private readonly maxBackoffMs: number;
  private readonly pollIntervalMs: number;

  constructor(private readonly config: ChatSyncConfig) {
    this.initialBackoffMs = config.initialBackoffMs ?? SYNC_INITIAL_BACKOFF_MS;
    this.maxBackoffMs = config.maxBackoffMs ?? SYNC_MAX_BACKOFF_MS;
    this.pollIntervalMs = config.pollInterval ?? 5000;
    this.nextBackoffMs = this.initialBackoffMs;
  }

  get lastSyncTimestamp(): string {
    return this.lastSync;
  }

  connect(since: string): void {
    this.disconnect();
    this.lastSync = since;
    this.connected = true;
    this.nextBackoffMs = this.initialBackoffMs;
    if (this.config.mode === 'sse') {
      this.startSSE();
    } else {
      this.scheduleTick(0);
    }
  }

  disconnect(): void {
    this.connected = false;
    this.generation += 1;
    this.pollInFlight = false;
    this.clearTimer();
    this.source?.close();
    this.source = null;
  }

  retryNow(): void {
    if (!this.connected) return;
    if (this.config.mode !== 'sse' && this.pollInFlight) return;
    this.generation += 1;
    this.nextBackoffMs = this.initialBackoffMs;
    this.clearTimer();
    if (this.config.mode === 'sse') {
      this.source?.close();
      this.source = null;
      this.startSSE();
    } else {
      this.scheduleTick(0);
    }
  }

  private applyFrame(payload: unknown): boolean {
    if (!ChatSyncService.isSyncResponse(payload)) return false;
    this.lastSync = payload.now;
    this.config.onSync(payload);
    this.emitStatus({ status: 'ok', lastError: null });
    this.nextBackoffMs = this.initialBackoffMs;

    return true;
  }

  private emitStatus(health: ChatSyncHealth): void {
    this.config.onStatus?.(health);
  }

  private clearTimer(): void {
    if (this.timer !== null) {
      clearTimeout(this.timer);
      this.timer = null;
    }
  }

  private scheduleTick(delayMs: number): void {
    this.clearTimer();
    const generation = this.generation;
    this.timer = setTimeout(() => {
      this.timer = null;
      if (generation !== this.generation || !this.connected) return;
      void this.poll(generation);
    }, delayMs);
  }

  private scheduleSseReconnect(): void {
    this.clearTimer();
    const delayMs = this.nextBackoffMs;
    this.nextBackoffMs = Math.min(this.maxBackoffMs, this.nextBackoffMs * 2);
    const generation = this.generation;
    this.timer = setTimeout(() => {
      this.timer = null;
      if (generation !== this.generation || !this.connected) return;
      this.startSSE();
    }, delayMs);
  }

  private failChannel(message: string): void {
    this.emitStatus({ status: 'retrying', lastError: message });
  }

  private async poll(generation: number): Promise<void> {
    if (generation !== this.generation || !this.connected) return;
    if (this.pollInFlight) return;
    const syncApi = this.config.getSyncApi;
    if (!syncApi) return;
    this.pollInFlight = true;
    try {
      const res = await syncApi().sync(this.lastSync);
      if (generation !== this.generation || !this.connected) return;
      if (this.applyFrame(res)) {
        this.scheduleTick(this.pollIntervalMs);

        return;
      }
      this.failChannel('Некорректный ответ синхронизации');
      this.scheduleBackoffPoll();
    } catch (error) {
      if (generation !== this.generation || !this.connected) return;
      const message = error instanceof Error ? error.message : 'Не удалось синхронизировать чат';
      this.failChannel(message);
      this.scheduleBackoffPoll();
    } finally {
      if (generation === this.generation) this.pollInFlight = false;
    }
  }

  private scheduleBackoffPoll(): void {
    const delayMs = this.nextBackoffMs;
    this.nextBackoffMs = Math.min(this.maxBackoffMs, this.nextBackoffMs * 2);
    this.scheduleTick(delayMs);
  }

  private static isSyncResponse(value: unknown): value is SyncResponse {
    if (typeof value !== 'object' || value === null) return false;
    const row = value as Record<string, unknown>;
    if (typeof row.now !== 'string' || !Array.isArray(row.chats) || !Array.isArray(row.newChats)) return false;
    if (typeof row.messages !== 'object' || row.messages === null || Array.isArray(row.messages)) return false;

    return Object.values(row.messages).every((entry) => Array.isArray(entry));
  }

  private startSSE(): void {
    const url = `${this.config.baseUrl ?? ''}/api/chat/sync?since=${encodeURIComponent(this.lastSync)}`;
    const source = new EventSource(url, { withCredentials: true });
    this.source = source;
    source.addEventListener('sync', (e: MessageEvent) => {
      if (this.source !== source || !this.connected) return;
      try {
        this.applyFrame(JSON.parse(e.data));
      } catch {
        // Событие-мусор с сервера не должно ронять синхронизацию.
      }
    });
    source.onerror = () => {
      if (this.source !== source || !this.connected) return;
      source.close();
      this.source = null;
      this.failChannel('Соединение чата прервано');
      this.scheduleSseReconnect();
    };
  }
}
