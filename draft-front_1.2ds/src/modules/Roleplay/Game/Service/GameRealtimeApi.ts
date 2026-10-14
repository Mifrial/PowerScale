import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { SseHandle } from '@/modules/Core/Engine/Dto/SseHandle';
import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';
import type { GameRealtimeSyncRequest } from '@/modules/Roleplay/Game/Dto/GameRealtimeSyncRequest';
import type { GameRealtimeSyncResult } from '@/modules/Roleplay/Game/Dto/GameRealtimeSyncResult';
import type { IGameRealtimePort } from '@/modules/Roleplay/Game/Interface/IGameRealtimePort';
import { GameRealtimeApiError } from '@/modules/Roleplay/Game/Service/GameRealtimeApiError';

/**
 * SSE /api/game/sync. Курсор внутри API; game.sync не вызывается.
 */
export class GameRealtimeApi implements IGameRealtimePort {
  private lastCursor: number | null = null;
  private handle: SseHandle | null = null;

  constructor(private readonly engine: Engine) {}

  async sync(
    _gameId: number,
    _request: GameRealtimeSyncRequest,
    _signal?: AbortSignal,
  ): Promise<GameRealtimeSyncResult> {
    throw new GameRealtimeApiError('GAME_REALTIME_UNAVAILABLE', 'Game realtime sync failed');
  }

  subscribe(
    gameId: number,
    listener: (event: GameRealtimeEvent) => void,
    onError?: (error: Error) => void,
  ): () => void {
    this.handle?.close();
    const query: Record<string, string | number | undefined> = { gameId };
    if (this.lastCursor !== null) query.lastCursor = this.lastCursor;
    this.handle = this.engine.openSse('/game/sync', query, {
      onEvent: (eventName, payload) => {
        if (eventName !== 'sync') return;
        this.applyFrame(gameId, payload, listener);
      },
      onError: (error) => {
        onError?.(new GameRealtimeApiError(error.code, error.message));
      },
    });

    return () => {
      this.handle?.close();
      this.handle = null;
    };
  }

  private applyFrame(gameId: number, payload: unknown, listener: (event: GameRealtimeEvent) => void): void {
    if (!this.isFrame(payload)) return;
    this.lastCursor = payload.cursor;
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
