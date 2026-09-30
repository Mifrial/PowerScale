import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';

export interface GameRuntimeEntityBatchResult {
  projections: GameRuntimeEntityProjection[];
  missingEntityKeys: CombatEntityKey[];
}
