import type { GameCombatConflict } from '@/modules/Roleplay/Game/Dto/GameCombatConflict';
import type { GameCombatEffect } from '@/modules/Roleplay/Game/Dto/GameCombatEffect';
import type { GameCombatProcessTransition } from '@/modules/Roleplay/Game/Dto/GameCombatProcessTransition';

/** Контракт ответа будущей Game command без локального расчёта combat outcome. */
export interface GameAuthoritativeCommandResult {
  commandId: string;
  status: 'accepted' | 'applied' | 'rejected';
  battleId: string | null;
  process: GameCombatProcessTransition;
  effects: GameCombatEffect[];
  affectedEntities: {
    kind: 'character' | 'npc';
    id: number;
    actualVersion: number;
    changedSections: string[];
  }[];
  conflict: GameCombatConflict | null;
}
