import { describe, it, expect } from 'vitest';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';

const service = new FormulaEvaluationService();

const context: FormulaContext = {
  characteristicValues: new Map([
    ['strength', { base: 5, size: 0 }],
    ['dexterity', { base: 6, size: 0 }],
  ]),
  abilityLevels: new Map([['melee-fighting', 3]]),
};

describe('FormulaEvaluationService', () => {
  it('fixed возвращает фиксированное значение', () => {
    expect(service.evaluate({ type: 'fixed', value: 4 }, context)).toBe(4);
  });

  it('characteristic берёт значение характеристики и добавляет модификатор в пунктах шкалы', () => {
    // +2 на базе 5 (максимум) переносит в следующий размер: {4, 1} = 8.
    expect(service.evaluate({ type: 'characteristic', characteristic_code: 'strength', modifier: 2 }, context)).toBe(8);
  });

  it('characteristic при отсутствии характеристики в контексте даёт только модификатор', () => {
    expect(service.evaluate({ type: 'characteristic', characteristic_code: 'magic', modifier: 1 }, context)).toBe(1);
  });

  it('модификатор −3 переносит Силу 5 средних в 5↓ (маленькие), а не в 2', () => {
    expect(
      service.evaluateDimensional({ type: 'characteristic', characteristic_code: 'strength', modifier: -3 }, context),
    ).toEqual({ base: 5, size: -1 });
    expect(service.evaluate({ type: 'characteristic', characteristic_code: 'strength', modifier: -3 }, context)).toBe(
      2,
    );
  });

  it('модификатор −1 не переносит размер: Сила 5 → 4', () => {
    expect(
      service.evaluateDimensional({ type: 'characteristic', characteristic_code: 'strength', modifier: -1 }, context),
    ).toEqual({ base: 4, size: 0 });
  });

  it('ability_level умножает уровень на multiplier и добавляет offset', () => {
    expect(
      service.evaluate({ type: 'ability_level', ability_code: 'melee-fighting', multiplier: 2, offset: 1 }, context),
    ).toBe(7);
  });

  it('ability_level без multiplier/offset трактует их как 1 и 0', () => {
    expect(service.evaluate({ type: 'ability_level', ability_code: 'melee-fighting' }, context)).toBe(3);
  });

  it('dimensional округляет base × 2^size вниз', () => {
    expect(service.evaluate({ type: 'dimensional', base: 3, size: 1 }, context)).toBe(6);
    expect(service.evaluate({ type: 'dimensional', base: 3, size: -1 }, context)).toBe(1);
  });

  it('parameter умножает значение параметра на per_unit', () => {
    const withResolver: FormulaContext = {
      ...context,
      parameterValues: (code) => (code === 'x' ? 2 : 1),
    };
    expect(service.evaluate({ type: 'parameter', parameter_code: 'x', per_unit: 2 }, withResolver)).toBe(4);
    // Без резолвера — значение параметра = 1.
    expect(service.evaluate({ type: 'parameter', parameter_code: 'x', per_unit: 3 }, context)).toBe(3);
  });

  it('characteristic_size возвращает размер характеристики (простое число)', () => {
    const sized: FormulaContext = {
      ...context,
      characteristicValues: new Map([
        ['dexterity', { base: 3, size: -1 }],
        ['strength', { base: 5, size: 1 }],
      ]),
    };
    expect(service.evaluate({ type: 'characteristic_size', characteristic_code: 'dexterity' }, sized)).toBe(-1);
    expect(service.evaluate({ type: 'characteristic_size', characteristic_code: 'strength' }, sized)).toBe(1);
  });

  it('characteristic_size для отсутствующей/безразмерной характеристики — 0', () => {
    expect(service.evaluate({ type: 'characteristic_size', characteristic_code: 'magic' }, context)).toBe(0);
  });

  it('characteristic_size_gap — число полных размеров, на которое from выше to', () => {
    const sized: FormulaContext = {
      ...context,
      characteristicValues: new Map([
        // {3|1}=6 vs {5|0}=5: разница меньше полного размера → 0.
        ['strength', { base: 3, size: 1 }],
        ['weight', { base: 5, size: 0 }],
      ]),
    };
    expect(
      service.evaluate(
        { type: 'characteristic_size_gap', characteristic_code_from: 'strength', characteristic_code_to: 'weight' },
        sized,
      ),
    ).toBe(0);

    // {3|1}=6 vs {3|0}=3: ровно один полный размер → 1.
    sized.characteristicValues.set('weight', { base: 3, size: 0 });
    expect(
      service.evaluate(
        { type: 'characteristic_size_gap', characteristic_code_from: 'strength', characteristic_code_to: 'weight' },
        sized,
      ),
    ).toBe(1);

    // Наоборот: {3|0} vs {3|1} → −1 (from ниже to на полный размер).
    sized.characteristicValues.set('strength', { base: 3, size: 0 });
    sized.characteristicValues.set('weight', { base: 3, size: 1 });
    expect(
      service.evaluate(
        { type: 'characteristic_size_gap', characteristic_code_from: 'strength', characteristic_code_to: 'weight' },
        sized,
      ),
    ).toBe(-1);

    // Остаток меньше полного размера отбрасывается в сторону нуля.
    sized.characteristicValues.set('strength', { base: 3, size: 0 });
    sized.characteristicValues.set('weight', { base: 5, size: 0 });
    expect(
      service.evaluate(
        { type: 'characteristic_size_gap', characteristic_code_from: 'strength', characteristic_code_to: 'weight' },
        sized,
      ),
    ).toBe(0);
  });

  it('evaluateDimensionalValue переводит DimensionalNumberValue в число', () => {
    expect(service.evaluateDimensionalValue({ base: 4, size: 0 })).toBe(4);
    expect(service.evaluateDimensionalValue({ base: 3, size: -1 })).toBe(1);
  });
});
