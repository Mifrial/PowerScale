import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';
import type { GameRuntimeEntityBatchRequest } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityBatchRequest';
import type { GameRuntimeEntityBatchResult } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityBatchResult';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Game-owned read boundary для нужных runtime entities без загрузки полного roster. */
export interface IGameRuntimeProjectionSource {
  getRuntimeEntity(
    gameId: number,
    entityKey: CombatEntityKey,
    projectionLevel: 'summary' | 'full',
    signal?: AbortSignal,
  ): Promise<GameRuntimeEntityProjection | null>;
  getRuntimeEntities(
    gameId: number,
    request: GameRuntimeEntityBatchRequest,
    signal?: AbortSignal,
  ): Promise<GameRuntimeEntityBatchResult>;
}
