import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import { DAMAGE_TYPE_FORMS } from '@/modules/Roleplay/Rule/init';
import { WEAPON_PROFILE_LABELS } from '@/modules/Roleplay/Character/Constant/WEAPON_PROFILE_LABELS';
import { formulaLabel } from '@/modules/Roleplay/Character/Utils/formulaLabel';
import { weaponAttackRangeService } from '@/modules/Roleplay/Character/Service/Instance/weaponAttackRangeService';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { ResistanceSlot } from '@/modules/Roleplay/Rule/Dto/Item/ResistanceSlot';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import type { ItemParamsView } from '@/modules/Roleplay/Character/Dto/ItemParamsView';
import type { WeaponProfileView } from '@/modules/Roleplay/Character/Dto/WeaponProfileView';

/** Подпись источника защиты: «от доспеха», «от поддоспешника». */
const DEFENSE_SOURCE_LABELS: Record<string, string> = {
  armor: 'доспеха',
  underarmor: 'поддоспешника',
};

export class ItemWeaponProfilesService {
  private readonly formula = new FormulaEvaluationService();

  /** Параметры оружия/щита/доспеха; null для не-снаряжения. */
  itemParamsView(
    spec: ItemSpec | undefined,
    characteristicValues: Map<string, DimensionalNumberValue>,
    rules: Rule[],
  ): ItemParamsView | null {
    if (!spec) return null;
    const weapon = spec.weapon;
    const shield = spec.shield;
    const armor = spec.armor;
    if (!weapon && !shield && !armor) return null;

    const resolveName = (code: string): string | null => rules.find((rule) => rule.code === code)?.name ?? null;

    const weightLabel =
      spec.innate || spec.weight === null || spec.weight === undefined
        ? null
        : `${new DimensionalNumber(spec.weight).toString()} кг`;
    const minStrength = weapon?.min_strength ?? shield?.min_strength ?? null;
    const minStrengthLabel =
      minStrength === null || minStrength === undefined ? null : new DimensionalNumber(minStrength).toString();
    const durability = weapon?.durability ?? shield?.durability;
    const durabilityLabel = durability === undefined ? null : new DimensionalNumber(durability).toString();
    const block = weapon?.block_profile ?? shield?.block ?? null;
    const blockDefenseLabel = block === null ? null : new DimensionalNumber(block.defense).toString();
    const blockEfficiencyLabel = block === null ? null : new DimensionalNumber(block.efficiency).toString();

    const limits = [...(armor?.characteristic_limits ?? []), ...(shield?.characteristic_limits ?? [])];
    let characteristicLimitsLabel: string | null = null;
    if (limits.length > 0) {
      const context: FormulaContext = { characteristicValues, abilityLevels: new Map() };
      const codes = limits.map((entry) => entry.characteristic_code);
      const isAgilityReaction = codes.length === 2 && codes.includes('dexterity') && codes.includes('reaction');
      if (isAgilityReaction) {
        const first = limits[0];
        const value = new DimensionalNumber(this.formula.evaluateDimensional(first.limit, context)).toString();
        characteristicLimitsLabel = `Макс. Ловкость/Реакция: ${value} (${formulaLabel(first.limit, resolveName)})`;
      } else {
        characteristicLimitsLabel = limits
          .map((entry) => {
            const value = new DimensionalNumber(this.formula.evaluateDimensional(entry.limit, context)).toString();
            const name = resolveName(entry.characteristic_code) ?? entry.characteristic_code;

            return `${name}: ${value} (${formulaLabel(entry.limit, resolveName)})`;
          })
          .join(' · ');
      }
    }

    const resistanceLabels = [...(block?.resistances ?? []), ...(armor?.resistance_slots ?? [])].map((slot) =>
      this.resistanceLabel(slot),
    );

    const maxAgilityLabel =
      armor?.max_agility === undefined || armor?.max_agility === null
        ? null
        : new DimensionalNumber(armor.max_agility).toString();
    const strengthPenaltyLabel =
      armor?.strength_penalty === undefined || armor?.strength_penalty === null ? null : String(armor.strength_penalty);
    const defenseLines = (armor?.defense_slots ?? []).map((slot) => ({
      defense: new DimensionalNumber(slot.defense).toString(),
      sourceLabel: this.defenseSourceLabel(slot.source_code),
      durability: slot.durability,
    }));

    return {
      weightLabel,
      minStrengthLabel,
      durabilityLabel,
      blockDefenseLabel,
      blockEfficiencyLabel,
      characteristicLimitsLabel,
      resistanceLabels,
      maxAgilityLabel,
      strengthPenaltyLabel,
      defenseLines,
    };
  }

  /**
   * Профили оружия/щита для панели предмета: значения урона/пробития/дальности оцениваются по текущим
   * характеристикам персонажа (FormulaContext). Для actionCharacteristic учитывается база действия
   * профиля (action_characteristics): у арбалета «Сила выстрела» фиксирована — дистанция считается
   * от неё, а не от текущей Силы.
   */
  weaponProfileViews(
    spec: ItemSpec | undefined,
    context: FormulaContext,
    resolveName: (code: string) => string | null,
  ): WeaponProfileView[] {
    const profiles = this.attackProfilesOf(spec);

    return profiles.map((profile) => {
      const profileContext: FormulaContext = weaponAttackRangeService.profileFormulaContext(
        profile,
        context,
        this.formula,
      );

      const damage = new DimensionalNumber(
        this.formula.evaluateDimensional(profile.damage.formula, profileContext),
      ).toString();
      const penetration = new DimensionalNumber(
        this.formula.evaluateDimensional(profile.penetration, profileContext),
      ).toString();
      const distance = new DimensionalNumber(
        this.formula.evaluateDimensional(profile.distance, profileContext),
      ).toString();
      const range =
        profile.range === null
          ? null
          : new DimensionalNumber(this.formula.evaluateDimensional(profile.range, profileContext)).toString();
      const accuracy = new DimensionalNumber(profile.accuracy).toString();
      const type = this.damageTypeLabel(profile.damage.damage_type_code);

      return {
        profileTypeLabel: WEAPON_PROFILE_LABELS[profile.type],
        damageLabel: type ? `${damage} ${type}` : damage,
        damageFormula: this.formulaOrNull(profile.damage.formula, resolveName),
        penetrationLabel: `${penetration} пробития`,
        penetrationFormula: this.formulaOrNull(profile.penetration, resolveName),
        accuracyLabel: accuracy,
        distanceLabel: range === null ? distance : `${distance}/${range}`,
        falloffLabel: profile.falloff === undefined ? null : new DimensionalNumber(profile.falloff).toString(),
      };
    });
  }

  private damageTypeLabel(code: string | null): string {
    if (code === null) return '';
    const genitive = DAMAGE_TYPE_FORMS[code]?.genitive ?? '';

    return genitive.replace(/ урона$/, '');
  }

  private formulaOrNull(formulaValue: Formula, resolveName: (code: string) => string | null): string | null {
    return formulaValue.type === 'fixed' ? null : formulaLabel(formulaValue, resolveName);
  }

  private attackProfilesOf(spec: ItemSpec | undefined): WeaponProfile[] {
    return spec?.weapon?.weapon_profiles ?? spec?.shield?.weapon_profiles ?? [];
  }

  private defenseSourceLabel(sourceCode: string | null): string | null {
    return sourceCode === null ? null : (DEFENSE_SOURCE_LABELS[sourceCode] ?? sourceCode);
  }

  private resistanceLabel(slot: ResistanceSlot): string {
    const dative = slot.damage_type_code === null ? '' : (DAMAGE_TYPE_FORMS[slot.damage_type_code]?.dative ?? '');

    return dative
      ? `Сопротивление ${dative}: ${new DimensionalNumber(slot.value).toString()}`
      : `Сопротивление: ${new DimensionalNumber(slot.value).toString()}`;
  }
}
