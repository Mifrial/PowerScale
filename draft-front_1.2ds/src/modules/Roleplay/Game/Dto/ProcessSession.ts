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
  /** Накопленное Комбо; только сессия процесса, не лист. */
  comboCount?: number;
  /** Цель Комбо; все шаги и закрытие только по ней. */
  comboTargetKey?: CombatEntityKey;
  /** Сколько раз экземпляр оружия уже завершил удар в этой сессии. */
  weaponUseCounts?: Record<string, number>;
  /** Последняя цель удара процесса; подставляется в следующий шаг. */
  lastStrikeTargetKey?: CombatEntityKey;
}
