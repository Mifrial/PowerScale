import type { SyncResponse } from '@/modules/Messages/Chat/Dto/SyncResponse';
import type { ChatSyncConfig } from '@/modules/Messages/Chat/Dto/ChatSyncConfig';
import type { ChatSyncHealth } from '@/modules/Messages/Chat/Dto/ChatSyncHealth';
import type { ChatSyncCursor } from '@/modules/Messages/Chat/Dto/ChatSyncCursor';
import type { SseHandle } from '@/modules/Core/Engine/Dto/SseHandle';
import { SYNC_INITIAL_BACKOFF_MS } from '@/modules/Messages/Chat/Constant/Chat/SYNC_INITIAL_BACKOFF_MS';
import { SYNC_MAX_BACKOFF_MS } from '@/modules/Messages/Chat/Constant/Chat/SYNC_MAX_BACKOFF_MS';

/**
 * Живой канал чата: poll mock или SSE через Engine, курсор пары unix+id.
 */
export class ChatSyncService {
  private sseHandle: SseHandle | null = null;
  private timer: ReturnType<typeof setTimeout> | null = null;
  private lastCursor: ChatSyncCursor | null = null;
  private generation = 0;
  private connected = false;
  private pollInFlight = false;
  private liveInvalidRetried = false;
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

  get lastSyncCursor(): ChatSyncCursor | null {
    return this.lastCursor;
  }

  connect(cursor: ChatSyncCursor | null): void {
    this.disconnect();
    this.lastCursor = cursor;
    this.connected = true;
    this.liveInvalidRetried = false;
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
    this.sseHandle?.close();
    this.sseHandle = null;
  }

  retryNow(): void {
    if (!this.connected) return;
    if (this.config.mode !== 'sse' && this.pollInFlight) return;
    this.generation += 1;
    this.nextBackoffMs = this.initialBackoffMs;
    this.liveInvalidRetried = false;
    this.clearTimer();
    if (this.config.mode === 'sse') {
      this.sseHandle?.close();
      this.sseHandle = null;
      this.startSSE();
    } else {
      this.scheduleTick(0);
    }
  }

  private applyFrame(payload: unknown): boolean {
    if (!ChatSyncService.isSyncResponse(payload)) return false;
    this.lastCursor = { since: payload.now, afterId: payload.afterId };
    this.liveInvalidRetried = false;
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
      const res = await syncApi().sync(this.lastCursor?.since ?? 0);
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
    if (typeof row.now !== 'number' || !Number.isFinite(row.now)) return false;
    if (typeof row.afterId !== 'number' || !Number.isFinite(row.afterId)) return false;
    if (!Array.isArray(row.chats) || !Array.isArray(row.newChats)) return false;
    if (typeof row.messages !== 'object' || row.messages === null || Array.isArray(row.messages)) return false;

    return Object.values(row.messages).every((entry) => Array.isArray(entry));
  }

  private sseQuery(): Record<string, string | number | undefined> {
    if (this.lastCursor === null) return {};
    if (this.lastCursor.afterId > 0) {
      return { since: this.lastCursor.since, afterId: this.lastCursor.afterId };
    }

    return { since: this.lastCursor.since };
  }

  private handleInvalidCursor(message: string): void {
    if (this.lastCursor !== null) {
      this.lastCursor = null;
      this.liveInvalidRetried = true;
      this.startSSE();

      return;
    }
    if (!this.liveInvalidRetried) {
      this.liveInvalidRetried = true;
      this.startSSE();

      return;
    }
    this.failChannel(message);
  }

  private startSSE(): void {
    const engine = this.config.engine;
    if (!engine) return;
    this.sseHandle?.close();
    this.sseHandle = engine.openSse('/chat/sync', this.sseQuery(), {
      onEvent: (eventName, payload) => {
        if (eventName !== 'sync' || !this.connected) return;
        this.applyFrame(payload);
      },
      onError: (error) => {
        if (!this.connected) return;
        this.sseHandle?.close();
        this.sseHandle = null;
        if (error.code === 'CHAT_INVALID') {
          this.handleInvalidCursor(error.message);

          return;
        }
        this.failChannel(error.message);
        this.scheduleSseReconnect();
      },
    });
  }
}
