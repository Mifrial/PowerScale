import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Незавершённое действие, которое добирает ОД на следующих ходах. */
export interface CommittedActionSession {
  gameId: number;
  entityKey: CombatEntityKey;
  actionRuleCode: string;
  remainingOd: number;
  totalOd: number;
  targetKey: CombatEntityKey | null;
  stateIndices: number[];
  startedAt: string;
  updatedAt: string;
}
