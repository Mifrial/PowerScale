import { describe, expect, it } from 'vitest';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { ProcessNoticeService } from '@/modules/Roleplay/Game/Service/ProcessNoticeService';

const rules = [
  {
    id: null,
    code: 'series',
    type: 'ability',
    name: 'Серия ударов',
    description: '',
    spaceId: 1,
    mechanics: [],
    createdAt: 1767225600,
    spec: {
      type: 'process',
      process: {
        steps: [
          { code: 'part-1', name: 'Первая часть', costs: [], interruption: { mode: 'normal' } },
          { code: 'part-2', name: 'Вторая часть', costs: [], interruption: { mode: 'normal' } },
        ],
        start_step_code: 'part-1',
        transition: { mode: 'custom', edges: [{ from: 'part-1', to: 'part-2' }] },
      },
    },
  },
] as unknown as Rule[];

const session = (stepCode: string, status: ProcessSession['currentStepStatus']): ProcessSession => ({
  gameId: 1,
  entityKey: 'character:1',
  processRuleCode: 'series',
  currentStepCode: stepCode,
  currentStepStatus: status,
  status: 'active',
  startedAt: '',
  updatedAt: '',
});

describe('ProcessNoticeService', () => {
  const service = new ProcessNoticeService();

  it('сообщает о новом выполненном шаге и предлагает продолжить', () => {
    expect(
      service.completedStep(session('part-1', 'pending'), session('part-1', 'completed'), 'character:1', rules),
    ).toEqual({
      text: 'Серия ударов: шаг «Первая часть» выполнен',
      entityKey: 'character:1',
      continueLabel: 'Продолжить',
    });
  });

  it('не повторяет уведомление для того же выполненного шага', () => {
    expect(
      service.completedStep(session('part-1', 'completed'), session('part-1', 'completed'), 'character:1', rules),
    ).toBeNull();
  });

  it('не предлагает продолжить, если переходов нет', () => {
    const notice = service.completedStep(undefined, session('part-2', 'completed'), 'character:1', rules);

    expect(notice).toEqual({ text: 'Серия ударов: шаг «Вторая часть» выполнен', entityKey: 'character:1' });
  });

  it('сообщает о прерывании процесса', () => {
    expect(service.aborted('Серия ударов', 'character:1')).toEqual({
      text: 'Серия ударов: процесс прерван',
      entityKey: 'character:1',
    });
  });
});
