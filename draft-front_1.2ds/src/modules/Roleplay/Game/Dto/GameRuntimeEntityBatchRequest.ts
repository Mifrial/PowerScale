import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface GameRuntimeEntityBatchRequest {
  entityKeys: CombatEntityKey[];
  projectionLevel: 'summary' | 'full';
}
