import { describe, expect, it } from 'vitest';
import { processSpecService } from '@/modules/Roleplay/Rule/Service/Instance/processSpecService';

describe('ProcessSpecService step costs', () => {
  it('кладёт переданный код в шаг и не даёт снять первую такую стоимость', () => {
    const withStep = processSpecService.addStep(processSpecService.createEmpty(), 'stamina');
    expect(withStep.steps[0].costs).toEqual([{ resource_code: 'stamina', amount: 1 }]);
    expect(processSpecService.isMandatoryCost(withStep.steps[0].costs, 0, 'stamina')).toBe(true);

    const kept = processSpecService.removeStepCost(withStep, 0, 0, 'stamina');
    expect(kept.steps[0].costs).toHaveLength(1);

    const withExtra = processSpecService.addStepCost(withStep, 0, 'stamina');
    expect(withExtra.steps[0].costs[1]).toEqual({ resource_code: 'stamina', amount: 1 });
    expect(processSpecService.isMandatoryCost(withExtra.steps[0].costs, 1, 'stamina')).toBe(false);
    const trimmed = processSpecService.removeStepCost(withExtra, 0, 1, 'stamina');
    expect(trimmed.steps[0].costs).toHaveLength(1);
  });

  it('без кода не ставит ресурс на шаг и не делает стоимость обязательной', () => {
    const withStep = processSpecService.addStep(processSpecService.createEmpty(), '');
    expect(withStep.steps[0].costs).toEqual([]);

    const withBlank = processSpecService.addStepCost(withStep, 0, '');
    expect(withBlank.steps[0].costs).toEqual([{ resource_code: '', amount: 1 }]);
    expect(processSpecService.isMandatoryCost(withBlank.steps[0].costs, 0, '')).toBe(false);

    const removed = processSpecService.removeStepCost(withBlank, 0, 0, '');
    expect(removed.steps[0].costs).toEqual([]);
  });
});
