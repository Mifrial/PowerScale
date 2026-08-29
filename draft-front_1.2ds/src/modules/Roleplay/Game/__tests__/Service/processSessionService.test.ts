import { describe, expect, it } from 'vitest';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';

const movement: ProcessSpec = {
  start_step_code: 'walk',
  transition: { mode: 'chain', max_shift: 1, direction: 'both' },
  steps: [
    {
      code: 'walk',
      name: 'Ходьба',
      description: '',
      interruption: { mode: 'normal' },
      costs: [{ resource_code: 'action-points', amount: 1 }],
    },
    {
      code: 'run',
      name: 'Бег',
      description: '',
      interruption: { mode: 'normal' },
      costs: [{ resource_code: 'action-points', amount: 2 }],
    },
    {
      code: 'sprint',
      name: 'Спринт',
      description: '',
      interruption: { mode: 'normal' },
      costs: [{ resource_code: 'action-points', amount: 3 }],
    },
  ],
};

describe('ProcessSessionService', () => {
  it('starts on the declared step and resolves chain neighbors including repeat', () => {
    const session = processSessionService.start(1, 'character:1', 'movement', movement);

    expect(session.currentStepCode).toBe('walk');
    expect(session.currentStepStatus).toBe('pending');
    expect(processSessionService.availableSteps(movement, 'walk').map((step) => step.code)).toEqual(['walk', 'run']);
    expect(processSessionService.availableSteps(movement, 'run').map((step) => step.code)).toEqual([
      'walk',
      'run',
      'sprint',
    ]);
  });

  it('supports custom graph and explicit exits', () => {
    const spec: ProcessSpec = {
      ...movement,
      exit_step_codes: ['walk'],
      transition: {
        mode: 'custom',
        edges: [
          { from: 'walk', to: 'run' },
          { from: 'run', to: 'walk' },
          { from: 'run', to: 'run' },
          { from: 'sprint', to: 'run' },
        ],
      },
    };

    expect(processSessionService.availableSteps(spec, 'sprint').map((step) => step.code)).toEqual(['run']);
    expect(processSessionService.canExit(spec, 'walk')).toBe(true);
    expect(processSessionService.canExit(spec, 'run')).toBe(false);
  });

  it('restarts on configured failure or ends the process', () => {
    const session = processSessionService.start(1, 'character:1', 'movement', movement);

    expect(
      processSessionService.resolveStep(session, { ...movement, failure: 'restart_from_first' }, 'run', false)
        ?.currentStepCode,
    ).toBe('walk');
    expect(processSessionService.resolveStep(session, { ...movement, failure: 'end_action' }, 'run', false)).toBeNull();
  });

  it('accepts the initial step once, then follows its outgoing transitions', () => {
    const spec: ProcessSpec = {
      ...movement,
      transition: {
        mode: 'custom',
        edges: [
          { from: 'walk', to: 'run' },
          { from: 'run', to: 'run' },
        ],
      },
    };
    const session = processSessionService.start(1, 'character:1', 'movement', spec);

    const afterStart = processSessionService.resolveStep(session, spec, 'walk', true);
    if (!afterStart) throw new Error('Начальный шаг не был принят');
    expect(afterStart?.currentStepStatus).toBe('completed');
    expect(afterStart?.currentStepCode).toBe('walk');
    expect(() => processSessionService.resolveStep(afterStart, spec, 'walk', true)).toThrow(
      'Выбранный шаг недоступен из текущего шага',
    );
    expect(processSessionService.availableSteps(spec, 'walk').map((step) => step.code)).toEqual(['run']);
  });

  it('returns the resource cost of a step', () => {
    expect(processSessionService.stepCost(movement.steps[1], 'action-points')).toBe(2);
    expect(processSessionService.stepCost(movement.steps[1], 'qi')).toBe(0);
  });

  it('distinguishes normal interruption from an emergency-only step', () => {
    expect(processSessionService.canInterruptNormally(movement, 'walk')).toBe(true);
    const emergency = {
      ...movement,
      steps: [
        {
          ...movement.steps[0],
          interruption: {
            mode: 'emergency' as const,
            effects: [
              {
                type: 'after_action_until_resource_spent_check_modifier' as const,
                resource_code: 'action-points',
                amount: 1,
                check_codes: [],
                delta: -1,
              },
            ],
          },
        },
        ...movement.steps.slice(1),
      ],
    };

    expect(processSessionService.canInterruptNormally(emergency, 'walk')).toBe(false);
  });
});
