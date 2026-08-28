import { describe, expect, it } from 'vitest';
import { MovementDistanceExpressionService } from '@/modules/Roleplay/Rule/Service/MovementDistanceExpressionService';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

const service = new MovementDistanceExpressionService();
const context = {
  currentMovementStep: { base: 1, size: -5 },
  characteristicValues: new Map([
    ['strength', { base: 3, size: 1 }],
    ['weight', { base: 3, size: 0 }],
  ]),
};

describe('MovementDistanceExpressionService', () => {
  it('evaluates current step and size gap expressions exactly', () => {
    expect(
      new DimensionalNumber(
        service.evaluate(
          {
            type: 'size_gap_times_step',
            characteristic_code_from: 'strength',
            characteristic_code_to: 'weight',
            base_steps: 2,
            gap_multiplier: 1,
          },
          context,
        ),
      ).value,
    ).toEqual({ base: 3, size: -5 });
  });

  it('changes the size without converting to a JavaScript number', () => {
    expect(
      service.evaluate(
        {
          type: 'change_size',
          expression: { type: 'current_movement_step', multiplier: 1 },
          size_delta: -2,
        },
        context,
      ),
    ).toEqual({ base: 1, size: -7 });
  });
});
