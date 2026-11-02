import type { ProcessSessionStep } from '@/modules/Roleplay/Game/Dto/ProcessSessionStep';
import type { ProcessSessionClosing } from '@/modules/Roleplay/Game/Dto/ProcessSessionClosing';

/** Сессия процесса из ответа game.getBattleSheets. */
export interface GamePhpProcessSessionView {
  processId: number;
  processRuleCode: string;
  currentStepCode: string | null;
  stepStatus: 'pending' | 'completed';
  status?: string;
  counters?: Record<string, unknown>;
  availableSteps?: ProcessSessionStep[];
  closing?: ProcessSessionClosing | null;
  weaponUseCounts?: Record<string, number>;
  lastTargetType?: string | null;
  lastTargetId?: number | null;
}
