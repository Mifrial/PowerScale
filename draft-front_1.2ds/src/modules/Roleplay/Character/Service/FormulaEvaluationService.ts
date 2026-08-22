import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/Constant/CHARACTERISTIC_BASE_RANGE';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

/**
 * Оценка формул правил по значениям персонажа. Используется для производных величин
 * (урон/пробитие/точность атак), а не для пересчёта самой версии: версия хранит итоги.
 *
 * Значения характеристик — размерные числа. Модификатор формулы — в пунктах шкалы базы:
 * шаг размера = (max − min + 1) пунктов, поэтому «Сила − 3» (Сила 5 средних) даёт 5↓
 * (маленькие), а не 2.
 */
export class FormulaEvaluationService {
  evaluateDimensional(formula: Formula, context: FormulaContext): DimensionalNumberValue {
    switch (formula.type) {
      case 'fixed':
        return { base: formula.value, size: 0 };
      case 'characteristic': {
        const value = context.characteristicValues.get(formula.characteristic_code);
        if (!value) return { base: formula.modifier, size: 0 };

        return new DimensionalNumber(value).modify(formula.modifier, CHARACTERISTIC_BASE_RANGE).value;
      }
      case 'ability_level': {
        const level = context.abilityLevels.get(formula.ability_code) ?? 0;

        return { base: level * (formula.multiplier ?? 1) + (formula.offset ?? 0), size: 0 };
      }
      case 'dimensional':
        return { base: formula.base, size: formula.size };
      case 'parameter': {
        const value = context.parameterValues?.(formula.parameter_code) ?? 1;

        return { base: value * formula.per_unit, size: 0 };
      }
      case 'actionCharacteristic': {
        const base = context.actionCharacteristicValue?.(formula.action, formula.characteristic) ??
          context.characteristicValues.get(formula.characteristic) ?? { base: 0, size: 0 };
        // Модификаторы действия (сильный удар +2 и т.п.) появятся позже; пока — модификаторы формулы оружия.
        const totalDelta = formula.modifier.reduce((sum, entry) => sum + entry.delta, 0);
        const modified = new DimensionalNumber(base).modify(totalDelta, CHARACTERISTIC_BASE_RANGE);
        // Без множителя — размерное значение («Сила − 3» при Силе 5 → 5↓, D49). Множитель
        // («[Сила выстрела × 10]» — дистанция) — плоское число: результат вне шкалы характеристик.
        if (formula.multiplier) return { base: modified.toNumber() * formula.multiplier, size: 0 };

        return modified.value;
      }
      case 'characteristic_size': {
        // Размер характеристики (простое число): {3|-1} → −1; {5|1} → 1.
        const value = context.characteristicValues.get(formula.characteristic_code);

        return { base: value?.size ?? 0, size: 0 };
      }
      case 'characteristic_size_gap': {
        // Число полных размеров, на которое from выше to: trunc(modifyDiffTo(from, to) / 3).
        const from = context.characteristicValues.get(formula.characteristic_code_from);
        const to = context.characteristicValues.get(formula.characteristic_code_to);
        if (!from || !to) return { base: 0, size: 0 };
        const delta = CharacteristicNumber.from(from).modifyDiffTo(new DimensionalNumber(to));
        // || 0 нормализует −0 → 0.
        const gap = Math.trunc(delta / 3) || 0;

        return { base: gap, size: 0 };
      }
    }
  }

  evaluate(formula: Formula, context: FormulaContext): number {
    return new DimensionalNumber(this.evaluateDimensional(formula, context)).toNumber();
  }

  evaluateDimensionalValue(value: DimensionalNumberValue): number {
    return new DimensionalNumber(value).toNumber();
  }
}
