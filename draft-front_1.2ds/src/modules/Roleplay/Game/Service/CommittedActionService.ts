import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import { WAIT_ACTION_CODE } from '@/modules/Roleplay/Game/Utils/combatActions';

/**
 * Долг ОД у действия без атаки/реакции/процесса: оплата с нескольких ходов, эффект в конце.
 */
export class CommittedActionService {
  canStretch(action: CombatActionOption): boolean {
    if (action.code === WAIT_ACTION_CODE) return false;
    if (action.isAttack || action.isReaction || action.isProcess || action.isVariableCost) return false;

    return action.odCost >= 1;
  }

  warnText(name: string, cost: number, available: number): string {
    return `«${name}» стоит ${cost} ОД, сейчас ${available}. Не хватит в этом ходу — действие займёт следующие ходы, пока не доберёшь остаток. Пока не закончишь, другие действия недоступны.`;
  }

  pay(remainingOd: number, available: number): { spent: number; remainingOd: number } {
    const spent = Math.min(Math.max(0, remainingOd), Math.max(0, available));

    return { spent, remainingOd: Math.max(0, remainingOd - spent) };
  }

  start(input: {
    gameId: number;
    entityKey: CombatEntityKey;
    actionRuleCode: string;
    totalOd: number;
    spent: number;
    targetKey: CombatEntityKey | null;
    stateIndices: number[];
    now?: string;
  }): CommittedActionSession {
    const now = input.now ?? new Date().toISOString();

    return {
      gameId: input.gameId,
      entityKey: input.entityKey,
      actionRuleCode: input.actionRuleCode,
      remainingOd: Math.max(0, input.totalOd - input.spent),
      totalOd: input.totalOd,
      targetKey: input.targetKey,
      stateIndices: [...input.stateIndices],
      startedAt: now,
      updatedAt: now,
    };
  }

  continueWith(session: CommittedActionSession, spent: number, now?: string): CommittedActionSession {
    const paid = this.pay(session.remainingOd, spent);

    return {
      ...session,
      remainingOd: paid.remainingOd,
      updatedAt: now ?? new Date().toISOString(),
    };
  }

  isComplete(session: CommittedActionSession): boolean {
    return session.remainingOd <= 0;
  }
}
