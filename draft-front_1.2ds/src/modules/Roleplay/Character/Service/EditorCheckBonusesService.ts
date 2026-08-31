import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { EditorCheckBonus } from '@/modules/Roleplay/Character/Dto/Editor/EditorCheckBonus';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';
import { abilityCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/abilityCheckAdvantagesService';
import { itemCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/itemCheckAdvantagesService';

export class EditorCheckBonusesService {
  constructor(
    private readonly aggregate = aggregateSourceDeltasService,
    private readonly abilityChecks = abilityCheckAdvantagesService,
    private readonly itemChecks = itemCheckAdvantagesService,
  ) {}
  build(
    version: Pick<CharacterVersion, 'abilities' | 'inventory'> | null | undefined,
    rules: Rule[],
  ): EditorCheckBonus[] {
    if (!version) return [];

    return rules
      .filter((rule) => rule.type === 'check')
      .map((check) => {
        const modifiers = [
          ...this.abilityChecks.checkAdvantageModifiersFromAbilities(version, rules, {
            kind: 'check',
            code: check.code,
          }),
          ...this.itemChecks.checkAdvantageModifiersFromItems(version, rules, {
            kind: 'check',
            code: check.code,
          }),
          ...this.characteristicModifiersFromAbilities(version, rules, check.code),
        ];
        const aggregated = this.aggregate.aggregateSourceDeltas(modifiers).map((modifier) => ({
          sourceRuleId: modifier.source_code,
          sourceLabel: modifier.source_label,
          delta: modifier.delta,
        }));
        const delta = aggregated.reduce((sum, modifier) => sum + modifier.delta, 0);

        return {
          checkCode: check.code,
          checkName: check.name,
          delta,
          modifiers: aggregated,
        };
      })
      .filter((bonus) => bonus.delta !== 0);
  }

  private characteristicModifiersFromAbilities(
    version: Pick<CharacterVersion, 'abilities'>,
    rules: Rule[],
    checkCode: string,
  ): AdvantageModifier[] {
    const modifiers: AdvantageModifier[] = [];
    for (const ability of version.abilities) {
      if (ability.level < 1) continue;
      const rule = rules.find((candidate) => candidate.id === ability.ruleId);
      if (!rule || rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;

      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          if (grant.type !== 'characteristic_modify' || !grant.check_codes?.includes(checkCode)) continue;
          if (grant.permanent === false && entry.level !== ability.level) continue;
          const delta = this.formulaValue(grant.amount, ability.parameters, version.abilities, rules);
          if (delta === 0) continue;
          modifiers.push({
            source_code: rule.id,
            source_label: rule.name,
            delta,
          });
        }
      }
    }

    return modifiers;
  }

  private formulaValue(
    formula: Formula,
    parameters: CharacterVersion['abilities'][number]['parameters'],
    abilities: CharacterVersion['abilities'],
    rules: Rule[],
  ): number {
    if (formula.type === 'fixed') return formula.value;
    if (formula.type === 'parameter' || formula.type === 'parameter_floor_div') {
      const raw = parameters?.[formula.parameter_code];
      const value = typeof raw === 'number' ? raw : (raw?.base ?? 0);
      if (formula.type === 'parameter') return value * formula.per_unit;

      return Math.floor(value / (formula.divisor || 1));
    }
    if (formula.type === 'ability_level') {
      const target = abilities.find(
        (ability) => rules.find((rule) => rule.id === ability.ruleId)?.code === formula.ability_code,
      );

      return (target?.level ?? 0) * (formula.multiplier ?? 1) + (formula.offset ?? 0);
    }

    return 0;
  }
}
