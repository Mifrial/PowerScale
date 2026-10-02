import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';
import type { GameRealtimeSyncRequest } from '@/modules/Roleplay/Game/Dto/GameRealtimeSyncRequest';
import type { GameRealtimeSyncResult } from '@/modules/Roleplay/Game/Dto/GameRealtimeSyncResult';

/** Game-owned sync/delivery boundary; it does not accept gameplay mutations. */
export interface IGameRealtimePort {
  sync(gameId: number, request: GameRealtimeSyncRequest, signal?: AbortSignal): Promise<GameRealtimeSyncResult>;
  subscribe(gameId: number, listener: (event: GameRealtimeEvent) => void, onError?: (error: Error) => void): () => void;
}
