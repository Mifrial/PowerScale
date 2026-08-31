import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ActionResolution } from '@/modules/Roleplay/Game/Dto/ActionResolution';

export interface ProcessSession {
  gameId: number;
  entityKey: CombatEntityKey;
  processRuleCode: string;
  currentStepCode: string;
  currentStepStatus: 'pending' | 'completed';
  status: 'active';
  startedAt: string;
  updatedAt: string;
  lastResolution?: ActionResolution;
}
