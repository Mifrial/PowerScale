import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { MovementDistanceExpression } from '@/modules/Roleplay/Rule/Dto/Ability/MovementDistanceExpression';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

export class MovementDistanceExpressionService {
  evaluate(
    expression: MovementDistanceExpression,
    context: {
      currentMovementStep: DimensionalNumberValue;
      characteristicValues: ReadonlyMap<string, DimensionalNumberValue>;
    },
  ): DimensionalNumberValue {
    switch (expression.type) {
      case 'steps':
        return expression.count === 0
          ? { base: 0, size: 0 }
          : this.multiply(context.currentMovementStep, expression.count);
      case 'literal':
        return expression.value;
      case 'current_movement_step':
        return this.multiply(context.currentMovementStep, expression.multiplier);
      case 'size_gap_times_step': {
        const from = context.characteristicValues.get(expression.characteristic_code_from);
        const to = context.characteristicValues.get(expression.characteristic_code_to);
        const gap =
          from && to ? Math.trunc(CharacteristicNumber.from(from).modifyDiffTo(new DimensionalNumber(to)) / 3) : 0;

        return this.multiply(context.currentMovementStep, expression.base_steps + expression.gap_multiplier * gap);
      }
      case 'change_size': {
        const value = new DimensionalNumber(this.evaluate(expression.expression, context));

        return { base: value.value.base, size: value.value.size + expression.size_delta };
      }
      case 'add': {
        let total = new DimensionalNumber({ base: 0, size: 0 });
        for (const child of expression.expressions)
          total = total.add(new DimensionalNumber(this.evaluate(child, context)));

        return total.value;
      }
    }
  }

  private multiply(value: DimensionalNumberValue, multiplier: number): DimensionalNumberValue {
    return { base: value.base * multiplier, size: value.size };
  }
}
