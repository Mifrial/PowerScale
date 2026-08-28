import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface ProcessSession {
  gameId: number;
  entityKey: CombatEntityKey;
  processRuleId: string;
  currentStepCode: string;
  currentStepStatus: 'pending' | 'completed';
  status: 'active';
  startedAt: string;
  updatedAt: string;
}
