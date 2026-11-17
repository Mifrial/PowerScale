import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Наложенный длительный эффект, не состояние листа. */
export interface GameActiveEffect {
  id: string;
  gameId: number;
  sourceType: string;
  sourceRuleCode: string;
  applierKey: string | null;
  targetKind: 'character' | 'npc';
  targetId: number;
  targetKey: CombatEntityKey;
  durationType: string;
  durationUnit: string;
  turnsTotal: number;
  turnsLeft: number;
  strength: DimensionalNumberValue;
  context: Record<string, unknown>;
  startedRound: number;
  startedParticipant: string;
  version: number;
}
