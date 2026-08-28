import { describe, expect, it } from 'vitest';
import { MovementContextService } from '@/modules/Roleplay/Character/Service/MovementContextService';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const service = new MovementContextService();

describe('MovementContextService', () => {
  it('uses the current movement step and complete strength-weight sizes', () => {
    const values = new Map([
      ['strength', { base: 5, size: 0 }],
      ['weight', { base: 3, size: 0 }],
    ]);

    expect(service.strengthWeightGap(values, 'strength', 'weight')).toBe(0);
    expect(
      service.strengthWeightGap(
        new Map([
          ['strength', { base: 3, size: 1 }],
          ['weight', { base: 3, size: 0 }],
        ]),
        'strength',
        'weight',
      ),
    ).toBe(1);
    expect(service.resolveMovementStep()).toEqual({ base: 1, size: 0 });
    expect(service.resolveMovementStep(undefined, undefined, { base: 1, size: -1 })).toEqual({ base: 1, size: -1 });
  });

  it('derives rule-defined run speed without losing dimensionality', () => {
    expect(service.speed({ base: 1, size: -5 }, 2, 1, 1)).toEqual({ base: 3, size: -5 });
  });

  it('resolves a racial automatic small-step ability from the current rules', () => {
    const version = { raceRuleId: 'race', abilities: [] } as CharacterVersion;
    const rules = [
      {
        id: 'race',
        code: 'turim',
        type: 'race',
        name: 'Турим',
        description: '',
        spaceId: 1,
        spec: { parent_race_code: null, abilities: [{ ability_code: 'small-step', automatic: true }] },
      },
      {
        id: 'small-step-rule',
        code: 'small-step',
        type: 'ability',
        name: 'Маленький шаг',
        description: '',
        spaceId: 1,
        spec: { type: 'trait', movement_step_size_delta: -1 },
      },
    ] as Rule[];

    expect(service.resolveMovementStep(version, rules)).toEqual({ base: 1, size: -1 });
  });
});
