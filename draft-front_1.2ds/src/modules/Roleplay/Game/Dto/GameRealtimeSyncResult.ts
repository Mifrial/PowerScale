import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';
import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';
import type { GameStateSnapshot } from '@/modules/Roleplay/Game/Dto/GameStateSnapshot';

/** Результат reconnect sync: missed events либо bounded snapshot. */
export type GameRealtimeSyncResult =
  | {
      kind: 'events';
      currentCursor: number;
      events: GameRealtimeEvent[];
    }
  | {
      kind: 'snapshot';
      currentCursor: number;
      snapshot: GameStateSnapshot;
      projections: GameRuntimeEntityProjection[];
    };
