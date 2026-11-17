import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { SpellCastDifficultyInput } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastDifficultyInput';
import type { SpellCastDifficultyResult } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastDifficultyResult';
import type { SpellCastResolveInput } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastResolveInput';
import { SPELL_CAST_BASE_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Spell/SPELL_CAST_BASE_DIFFICULTY';
import { SPELL_CAST_MIN_LIVE_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Spell/SPELL_CAST_MIN_LIVE_DIFFICULTY';
import { SPELL_CAST_SKIP_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Spell/SPELL_CAST_SKIP_DIFFICULTY';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

/** Сложность сотворения: дефицит ресурсов и сопротивление магии выбранной цели. */
export class SpellCastDifficultyService {
  asSpellAbilitySpec(rule: Rule | undefined): Extract<AbilitySpec, { type: 'spell' }> | null {
    if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') {
      return null;
    }
    if (!('type' in rule.spec) || rule.spec.type !== 'spell') {
      return null;
    }

    return rule.spec;
  }

  resolveSpellValue(
    value: SpellValue,
    parameterValues: Record<string, DimensionalNumberValue>,
  ): DimensionalNumberValue {
    if (!('base' in value)) {
      return parameterValues[value.parameter_code] ?? { base: 3, size: 0 };
    }

    return { base: value.base, size: value.size };
  }

  /** Пределы мощи поддержания из формы длительности: минимум и максимум параметра. */
  sustainPowerLimits(rule: Rule | undefined): {
    min: DimensionalNumberValue | null;
    max: DimensionalNumberValue | null;
  } {
    const spec = this.asSpellAbilitySpec(rule);
    const duration = spec?.spell.duration;
    if (!duration || duration.type !== 'sustained') {
      return { min: null, max: null };
    }
    const power = duration.power;
    if (!power) {
      return { min: null, max: null };
    }
    if ('base' in power) {
      const fixed = { base: power.base, size: power.size };

      return { min: fixed, max: fixed };
    }
    const parameter = spec?.parameters?.find((entry) => entry.code === power.parameter_code);
    if (!parameter) {
      return { min: null, max: null };
    }

    return { min: this.dimensionalLimit(parameter.min), max: this.dimensionalLimit(parameter.max) };
  }

  private dimensionalLimit(value: DimensionalNumberValue | number | null | undefined): DimensionalNumberValue | null {
    if (value === null || value === undefined) return null;

    return typeof value === 'number' ? { base: value, size: 0 } : value;
  }

  compute(input: SpellCastDifficultyInput): SpellCastDifficultyResult {
    const requiredPower = CharacteristicNumber.from(input.requiredPower).modifyWith(input.requiredPowerDelta ?? 0);
    const usedPower = CharacteristicNumber.from(input.usedPower);
    const requiredControl = CharacteristicNumber.from(input.requiredControl).modifyWith(
      input.requiredControlDelta ?? 0,
    );
    const availableControl = CharacteristicNumber.from(input.availableControl);
    const powerShortage = requiredPower.modifyDiffTo(usedPower);
    const controlShortage = requiredControl.modifyDiffTo(availableControl);
    let delta = 0;
    if (powerShortage > 0) {
      delta += powerShortage;
    }
    if (controlShortage > 0) {
      delta += controlShortage;
    }
    if (powerShortage < 0 && controlShortage < 0) {
      delta -= Math.min(-powerShortage, -controlShortage);
    }
    let difficulty = CharacteristicNumber.from(SPELL_CAST_BASE_DIFFICULTY).modifyWith(delta);
    const minLive = CharacteristicNumber.from(SPELL_CAST_MIN_LIVE_DIFFICULTY);
    if (difficulty.modifyDiffTo(minLive) < 0) {
      return {
        difficulty: SPELL_CAST_SKIP_DIFFICULTY,
        needsCheck: false,
        powerShortage,
        controlShortage,
      };
    }
    const resistance = Math.max(0, (input.resistanceModify ?? 0) - (input.resistancePenetration ?? 0));
    if (resistance !== 0) {
      difficulty = difficulty.modifyWith(resistance);
    }
    const trainingDelta = input.trainingDifficultyDelta ?? 0;
    if (trainingDelta !== 0) {
      difficulty = difficulty.modifyWith(trainingDelta);
    }
    if (difficulty.modifyDiffTo(minLive) < 0) {
      return {
        difficulty: SPELL_CAST_SKIP_DIFFICULTY,
        needsCheck: false,
        powerShortage,
        controlShortage,
      };
    }

    return {
      difficulty: difficulty.value,
      needsCheck: true,
      powerShortage,
      controlShortage,
    };
  }

  computeForSpell(input: SpellCastResolveInput, rules: Rule[]): SpellCastDifficultyResult {
    const rule = rules.find((entry) => entry.code === input.spellCode);
    const spec = this.asSpellAbilitySpec(rule);
    if (!spec) {
      return {
        difficulty: SPELL_CAST_SKIP_DIFFICULTY,
        needsCheck: false,
        powerShortage: 0,
        controlShortage: 0,
      };
    }

    const requiredPower = this.resolveSpellValue(spec.spell.power, input.parameterValues);
    const requiredControl = this.resolveSpellValue(spec.spell.control, input.parameterValues);
    const resistanceModify =
      spec.spell.resisted_by_magic === true ? (input.targetResistanceAmount ?? 0) : 0;

    return this.compute({
      requiredPower,
      usedPower: input.usedPower,
      requiredControl,
      availableControl: input.availableControl,
      resistanceModify,
      requiredPowerDelta: input.requiredPowerDelta,
      requiredControlDelta: input.requiredControlDelta,
      resistancePenetration: input.resistancePenetration,
      trainingDifficultyDelta: input.trainingDifficultyDelta,
    });
  }

  /**
   * Путь без Контроля: броска нет, успех решает сравнение мощи.
   *
   * Сопротивление и штраф тренировки сдвигают используемую мощь так же,
   * как они сдвигали бы Сложность обычного каста.
   */
  controllessOutcome(input: {
    requiredPower: DimensionalNumberValue;
    usedPower: DimensionalNumberValue;
    resistanceModify?: number;
    resistancePenetration?: number;
    requiredPowerDelta?: number;
    trainingDifficultyDelta?: number;
  }): {
    success: boolean;
    usedPower: DimensionalNumberValue;
    requiredPower: DimensionalNumberValue;
    effectivePower: DimensionalNumberValue;
    resistance: number;
  } {
    const required = CharacteristicNumber.from(input.requiredPower).modifyWith(input.requiredPowerDelta ?? 0);
    const resistance = Math.max(0, (input.resistanceModify ?? 0) - (input.resistancePenetration ?? 0));
    const shift = resistance + (input.trainingDifficultyDelta ?? 0);
    const used = CharacteristicNumber.from(input.usedPower);
    const effective = shift === 0 ? used : used.modifyWith(-shift);

    return {
      success: required.modifyDiffTo(effective) <= 0,
      usedPower: used.value,
      requiredPower: required.value,
      effectivePower: effective.value,
      resistance,
    };
  }

  formatControllessLine(outcome: {
    success: boolean;
    usedPower: DimensionalNumberValue;
    requiredPower: DimensionalNumberValue;
    effectivePower: DimensionalNumberValue;
    resistance: number;
  }): string {
    const used = this.formatDifficulty(outcome.usedPower);
    const required = this.formatDifficulty(outcome.requiredPower);
    const left =
      outcome.resistance > 0
        ? `${used} − сопротивление ${outcome.resistance} = ${this.formatDifficulty(outcome.effectivePower)}`
        : used;

    return `Сотворение по Мощи: ${left} против ${required} — ${outcome.success ? 'успех' : 'недостаточно'}`;
  }

  formatDifficulty(value: DimensionalNumberValue): string {
    return DimensionalNumber.from(value).toString();
  }

  /** Фиксированное требование или «х», если мощь/контроль — параметр. */
  formatSpellRequirement(value: DimensionalNumberValue | null): string {
    return value ? this.formatDifficulty(value) : 'х';
  }

  /** Строка проверки: характеристика кастера против сложности сотворения. */
  formatCastCheckLine(
    needsCheck: boolean,
    difficulty: DimensionalNumberValue,
    characteristicName: string | null,
    characteristicValue: DimensionalNumberValue | null,
  ): string {
    if (!needsCheck) {
      return 'Проверка на сотворение не требуется';
    }
    const against = this.formatDifficulty(difficulty);
    if (!characteristicName || !characteristicValue) {
      return `Проверка на сотворение против ${against}`;
    }

    return `Проверка на сотворение: ${characteristicName} ${this.formatDifficulty(characteristicValue)} против ${against}`;
  }

  /** Если value выше max по шкале характеристик, возвращает копию max. */
  clampToAtMost(value: DimensionalNumberValue, max: DimensionalNumberValue): DimensionalNumberValue {
    if (CharacteristicNumber.from(value).modifyDiffTo(CharacteristicNumber.from(max)) > 0) {
      return { base: max.base, size: max.size };
    }

    return { base: value.base, size: value.size };
  }

  /** Клампит размерное значение в пределы параметра, если они заданы. */
  clampToRange(
    value: DimensionalNumberValue,
    min: DimensionalNumberValue | null,
    max: DimensionalNumberValue | null,
  ): DimensionalNumberValue {
    const number = DimensionalNumber.from(value);
    if (min && number.compare(DimensionalNumber.from(min)) < 0) {
      return { base: min.base, size: min.size };
    }

    if (max && number.compare(DimensionalNumber.from(max)) > 0) {
      return { base: max.base, size: max.size };
    }

    return { base: value.base, size: value.size };
  }
}
