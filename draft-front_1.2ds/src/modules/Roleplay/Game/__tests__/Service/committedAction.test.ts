import { describe, expect, it } from 'vitest';
import { committedActionService } from '@/modules/Roleplay/Game/Service/Instance/committedActionService';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';

function action(partial: Partial<CombatActionOption>): CombatActionOption {
  return { ruleCode: 'perevyazat', code: 'perevyazat', name: 'Перевязать', odCost: 8, ...partial };
}

describe('CommittedActionService', () => {
  it('растягивает обычное действие и не трогает атаку, ожидание и процесс', () => {
    expect(committedActionService.canStretch(action({}))).toBe(true);
    expect(committedActionService.canStretch(action({ isAttack: true }))).toBe(false);
    expect(committedActionService.canStretch(action({ isReaction: true }))).toBe(false);
    expect(committedActionService.canStretch(action({ isProcess: true }))).toBe(false);
    expect(committedActionService.canStretch(action({ isVariableCost: true, code: 'wait' }))).toBe(false);
  });

  it('списывает доступное и оставляет остаток; полный платёж завершает', () => {
    expect(committedActionService.pay(8, 3)).toEqual({ spent: 3, remainingOd: 5 });
    expect(committedActionService.pay(5, 6)).toEqual({ spent: 5, remainingOd: 0 });
    const started = committedActionService.start({
      gameId: 1,
      entityKey: 'character:1',
      actionRuleCode: 'perevyazat',
      totalOd: 8,
      spent: 3,
      targetKey: 'character:2',
      stateIndices: [1],
      now: 't',
    });
    expect(started.remainingOd).toBe(5);
    const next = committedActionService.continueWith(started, 5, 't2');
    expect(committedActionService.isComplete(next)).toBe(true);
  });
});
