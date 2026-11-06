import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';

/** Game-owned delivery boundary: поток кадров, без чтения снимка. */
export interface IGameRealtimePort {
  subscribe(gameId: number, listener: (event: GameRealtimeEvent) => void, onError?: (error: Error) => void): () => void;
}
