import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Reconnect cursor и ограниченный набор projections для Game sync. */
export interface GameRealtimeSyncRequest {
  lastCursor: number | null;
  entityKeys?: CombatEntityKey[];
  projectionLevel?: 'summary' | 'full';
}
