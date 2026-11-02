/** Сессия процесса из ответа game.getBattleSheets. */
export interface GamePhpProcessSessionView {
  processId: number;
  processRuleCode: string;
  currentStepCode: string | null;
  stepStatus: 'pending' | 'completed';
  status?: string;
  counters?: Record<string, unknown>;
  weaponUseCounts?: Record<string, number>;
  lastTargetType?: string | null;
  lastTargetId?: number | null;
}
