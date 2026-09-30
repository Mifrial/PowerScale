import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';
import type { ScalarFormula } from '@/modules/Roleplay/Rule/Dto/Ability/ScalarFormula';
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
  evaluateScalar(formula: ScalarFormula, context: FormulaContext): number {
    switch (formula.type) {
      case 'fixed':
        return formula.value;
      case 'parameter':
        return this.parameterValue(context, formula.parameter_code) * formula.per_unit;
      case 'parameter_floor_div': {
        if (formula.divisor === 0) throw new Error('Делитель parameter_floor_div равен 0');

        return Math.floor(this.parameterValue(context, formula.parameter_code) / formula.divisor);
      }
      case 'ability_level': {
        const level = context.abilityLevels.get(formula.ability_code) ?? 0;

        return level * (formula.multiplier ?? 1) + (formula.offset ?? 0);
      }
      case 'to_scalar':
        return this.mediumSizeBase(this.evaluateDimensional(formula.value, context));
      case 'characteristic_size':
        // Нет характеристики — размер 0: лимит ОД считается и без неё. Это не подмена неизвестного узла.
        return context.characteristicValues.get(formula.characteristic_code)?.size ?? 0;
      case 'characteristic_size_positive':
        return Math.max(0, context.characteristicValues.get(formula.characteristic_code)?.size ?? 0);
      case 'characteristic_size_gap': {
        const from = context.characteristicValues.get(formula.characteristic_code_from);
        const to = context.characteristicValues.get(formula.characteristic_code_to);
        if (!from || !to) return 0;
        const delta = CharacteristicNumber.from(from).modifyDiffTo(new DimensionalNumber(to));

        return Math.trunc(delta / 3) || 0;
      }
    }
  }

  evaluateDimensional(formula: DimensionalFormula, context: FormulaContext): DimensionalNumberValue {
    switch (formula.type) {
      case 'fixed':
        return { base: formula.value, size: 0 };
      case 'dimensional':
        return { base: formula.base, size: formula.size };
      case 'characteristic': {
        const value = context.characteristicValues.get(formula.characteristic_code);
        if (!value) throw new Error(`Нет характеристики «${formula.characteristic_code}»`);

        return new DimensionalNumber(value).modify(formula.modifier, CHARACTERISTIC_BASE_RANGE).value;
      }
      case 'actionCharacteristic': {
        const base =
          context.actionCharacteristicValue?.(formula.action, formula.characteristic) ??
          context.characteristicValues.get(formula.characteristic);
        if (!base) throw new Error(`Нет характеристики «${formula.characteristic}» для ${formula.action}`);
        const totalDelta = formula.modifier.reduce((sum, entry) => sum + entry.delta, 0);
        const modified = new DimensionalNumber(base).modify(totalDelta, CHARACTERISTIC_BASE_RANGE).value;
        if (!formula.multiplier) return modified;

        return { base: modified.base * formula.multiplier, size: modified.size };
      }
    }
  }

  /** Скалярная формула. Размерная формула сюда не приводится. */
  evaluate(formula: ScalarFormula, context: FormulaContext): number {
    return this.evaluateScalar(formula, context);
  }

  /** Узел читает значения характеристик. Пустой контекст проверки для него — ошибка вызывающего. */
  readsCharacteristics(formula: ScalarFormula): boolean {
    return (
      formula.type === 'characteristic_size' ||
      formula.type === 'characteristic_size_positive' ||
      formula.type === 'characteristic_size_gap' ||
      formula.type === 'to_scalar'
    );
  }

  evaluateDimensionalValue(value: DimensionalNumberValue): number {
    return new DimensionalNumber(value).toNumber();
  }

  /** База значения, перенесённого на средний размер. Целые размеры базу шкалы 3–5 не меняют. */
  private mediumSizeBase(value: DimensionalNumberValue): number {
    const step = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;

    return new DimensionalNumber(value).modify(-value.size * step, CHARACTERISTIC_BASE_RANGE).value.base;
  }

  private parameterValue(context: FormulaContext, code: string): number {
    const value = context.parameterValues?.(code);
    if (value === undefined) throw new Error(`Нет параметра «${code}»`);

    return value;
  }
}
