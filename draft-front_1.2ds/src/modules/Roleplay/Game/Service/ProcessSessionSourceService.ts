import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GamePhpProcessSessionView } from '@/modules/Roleplay/Game/Dto/GamePhpProcessSessionView';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';

/** Переводит серверные сессии процесса в Dto боевой карточки. */
export class ProcessSessionSourceService {
  /**
   * Сессии процесса из game.getBattleSheets по ключам участников боя.
   *
   * @param gameId Игра.
   * @param views Серверные сессии или undefined.
   *
   * @returns Сессии процесса.
   */
  fromBattleSheets(
    gameId: number,
    views: Record<string, GamePhpProcessSessionView> | undefined,
  ): Record<CombatEntityKey, ProcessSession> {
    const sessions: Record<CombatEntityKey, ProcessSession> = {};
    for (const [entityKey, view] of Object.entries(views ?? {})) {
      const session = this.toSession(gameId, entityKey as CombatEntityKey, view);
      if (session) sessions[entityKey as CombatEntityKey] = session;
    }

    return sessions;
  }

  private toSession(
    gameId: number,
    entityKey: CombatEntityKey,
    view: GamePhpProcessSessionView,
  ): ProcessSession | null {
    if (!view.currentStepCode) return null;
    const lastStrikeTargetKey =
      view.lastTargetType && typeof view.lastTargetId === 'number'
        ? (`${view.lastTargetType}:${view.lastTargetId}` as CombatEntityKey)
        : undefined;
    const counters: Record<string, number> = {};
    for (const [code, value] of Object.entries(view.counters ?? {})) {
      if (typeof value === 'number') counters[code] = value;
    }

    return {
      gameId,
      entityKey,
      processRuleCode: view.processRuleCode,
      currentStepCode: view.currentStepCode,
      currentStepStatus: view.stepStatus === 'completed' ? 'completed' : 'pending',
      status: 'active',
      startedAt: '',
      updatedAt: '',
      counters,
      availableSteps: view.availableSteps,
      closing: view.closing ?? null,
      weaponUseCounts: view.weaponUseCounts,
      lastStrikeTargetKey,
    };
  }
}
