import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';
import type { GameRealtimeSyncRequest } from '@/modules/Roleplay/Game/Dto/GameRealtimeSyncRequest';
import type { GameRealtimeSyncResult } from '@/modules/Roleplay/Game/Dto/GameRealtimeSyncResult';
import type { IGameRealtimePort } from '@/modules/Roleplay/Game/Interface/IGameRealtimePort';
import { GameRealtimeApiError } from '@/modules/Roleplay/Game/Service/GameRealtimeApiError';

/** Request-response readiness adapter for future Game sync/SSE transport. */
export class GameRealtimeApi implements IGameRealtimePort {
  constructor(private readonly engine: Engine) {}

  async sync(
    gameId: number,
    request: GameRealtimeSyncRequest,
    signal?: AbortSignal,
  ): Promise<GameRealtimeSyncResult> {
    const response = await this.engine.runAction<GameRealtimeSyncResult>(
      'game.sync',
      { gameId, ...request },
      signal,
    );
    if (!response.success || response.data === null) {
      if (response.error) throw GameRealtimeApiError.fromActionError(response.error);

      throw new GameRealtimeApiError('GAME_REALTIME_EMPTY_RESPONSE', 'Game realtime sync failed');
    }

    return response.data;
  }

  subscribe(
    _gameId: number,
    _listener: (event: GameRealtimeEvent) => void,
    onError?: (error: Error) => void,
  ): () => void {
    onError?.(new GameRealtimeApiError('GAME_REALTIME_UNAVAILABLE', 'Game realtime transport unavailable'));

    return () => undefined;
  }
}
