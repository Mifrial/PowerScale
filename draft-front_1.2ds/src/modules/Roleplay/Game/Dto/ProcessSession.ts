import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface ProcessSession {
  gameId: number;
  entityKey: CombatEntityKey;
  processRuleId: string;
  currentStepCode: string;
  status: 'active';
  startedAt: string;
  updatedAt: string;
}
