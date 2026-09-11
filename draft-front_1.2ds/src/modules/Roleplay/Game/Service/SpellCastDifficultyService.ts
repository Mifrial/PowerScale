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
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/init';

/** Сложность сотворения: шкала характеристик, skip ниже живого минимума, resistance с флагом. */
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

  compute(input: SpellCastDifficultyInput): SpellCastDifficultyResult {
    const requiredPower = CharacteristicNumber.from(input.requiredPower);
    const usedPower = CharacteristicNumber.from(input.usedPower);
    const requiredControl = CharacteristicNumber.from(input.requiredControl);
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
    const resistance = input.resistanceModify ?? 0;
    if (resistance !== 0) {
      difficulty = difficulty.modifyWith(resistance);
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
    const damageTypeCode = input.damageTypeCode ?? spec.spell.damage?.damage_type_code ?? null;
    let resistanceModify = 0;
    if (input.hasTarget && damageTypeCode) {
      const damageRule = rules.find((entry) => entry.code === damageTypeCode);
      const damageSpec = damageTypeSpecService.asDamageTypeSpec(damageRule);
      if (damageSpec?.modifies_spell_difficulty) {
        resistanceModify = input.targetResistanceAmount ?? 0;
      }
    }

    return this.compute({
      requiredPower,
      usedPower: input.usedPower,
      requiredControl,
      availableControl: input.availableControl,
      resistanceModify,
    });
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
}
