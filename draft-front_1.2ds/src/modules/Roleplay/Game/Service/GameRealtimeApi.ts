import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { SseHandle } from '@/modules/Core/Engine/Dto/SseHandle';
import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';
import type { IGameRealtimePort } from '@/modules/Roleplay/Game/Interface/IGameRealtimePort';
import { GameRealtimeApiError } from '@/modules/Roleplay/Game/Service/GameRealtimeApiError';

/** Пауза перед переподключением: старт и потолок. */
const RECONNECT_MIN_MS = 1000;

const RECONNECT_MAX_MS = 5000;

/** Разброс переподключения, чтобы вкладки не били синхронно. */
const RECONNECT_JITTER_MS = 250;

/**
 * SSE /api/game/sync. Курсор внутри API; отдельного чтения снимка нет.
 *
 * Сервер закрывает поток по потолку жизни, поэтому поток переподключается
 * сам с последним курсором. Явное закрытие подписки переподключение отменяет.
 */
export class GameRealtimeApi implements IGameRealtimePort {
  private lastCursor: number | null = null;
  private lastGameId: number | null = null;
  private handle: SseHandle | null = null;
  private listener: ((event: GameRealtimeEvent) => void) | null = null;
  private errorListener: ((error: Error) => void) | null = null;
  private gameId: number | null = null;
  private generation = 0;
  private closed = true;
  private reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  private backoffMs = RECONNECT_MIN_MS;

  constructor(private readonly engine: Engine) {}

  subscribe(
    gameId: number,
    listener: (event: GameRealtimeEvent) => void,
    onError?: (error: Error) => void,
  ): () => void {
    this.stop();
    if (this.lastGameId !== gameId) {
      this.lastCursor = null;
      this.lastGameId = gameId;
    }
    this.gameId = gameId;
    this.listener = listener;
    this.errorListener = onError ?? null;
    this.closed = false;
    this.backoffMs = RECONNECT_MIN_MS;
    this.generation += 1;
    this.open(this.generation);

    return () => this.stop();
  }

  /** Останавливает поток и отменяет отложенное переподключение. */
  private stop(): void {
    this.closed = true;
    this.generation += 1;
    this.clearReconnect();
    this.handle?.close();
    this.handle = null;
    this.listener = null;
    this.errorListener = null;
  }

  /** Открывает одно соединение под текущее поколение подписки. */
  private open(generation: number): void {
    const gameId = this.gameId;
    if (gameId === null) return;
    const query: Record<string, string | number | undefined> = { gameId };
    if (this.lastCursor !== null) query.lastCursor = this.lastCursor;
    this.handle = this.engine.openSse('/game/sync', query, {
      onEvent: (eventName, payload) => {
        if (generation !== this.generation || this.closed) return;
        if (eventName !== 'sync') return;
        this.applyFrame(gameId, payload, this.listener);
      },
      onEnd: () => this.scheduleReconnect(generation),
      onError: (error) => {
        if (generation !== this.generation || this.closed) return;
        this.errorListener?.(new GameRealtimeApiError(error.code, error.message));
        this.scheduleReconnect(generation);
      },
    });
  }

  /** Планирует переподключение с backoff; штатное закрытие потока — не ошибка. */
  private scheduleReconnect(generation: number): void {
    if (generation !== this.generation || this.closed) return;
    this.handle = null;
    this.clearReconnect();
    const delay = this.backoffMs + Math.floor(Math.random() * RECONNECT_JITTER_MS);
    this.backoffMs = Math.min(RECONNECT_MAX_MS, this.backoffMs * 2);
    this.reconnectTimer = setTimeout(() => {
      this.reconnectTimer = null;
      if (generation !== this.generation || this.closed) return;
      this.open(this.generation);
    }, delay);
  }

  private clearReconnect(): void {
    if (this.reconnectTimer === null) return;
    clearTimeout(this.reconnectTimer);
    this.reconnectTimer = null;
  }

  private applyFrame(gameId: number, payload: unknown, listener: ((event: GameRealtimeEvent) => void) | null): void {
    if (!this.isFrame(payload)) return;
    this.lastCursor = payload.cursor;
    this.backoffMs = RECONNECT_MIN_MS;
    if (listener === null) return;
    for (const item of payload.events) {
      listener({
        gameId,
        eventId: String(item.eventId),
        cursor: item.cursor,
        actualVersion: 0,
        changedSections: [],
        sessionId: null,
        battleId: item.battleId ?? null,
        source: item.source,
        sourceId: item.sourceId,
        keys: item.keys,
      });
    }
  }

  private isFrame(payload: unknown): payload is {
    cursor: number;
    events: {
      eventId: string | number;
      cursor: number;
      source: string;
      sourceId: number;
      keys: unknown[];
      battleId?: number;
    }[];
  } {
    if (payload === null || typeof payload !== 'object') return false;
    const frame = payload as { cursor?: unknown; events?: unknown };
    if (typeof frame.cursor !== 'number' || !Array.isArray(frame.events)) return false;

    return true;
  }
}
