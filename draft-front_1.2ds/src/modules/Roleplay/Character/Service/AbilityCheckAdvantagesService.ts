import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CheckAdvantageQuery } from '@/modules/Roleplay/Character/Dto/CheckAdvantageQuery';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

export class AbilityCheckAdvantagesService {
  constructor(private readonly aggregate = aggregateSourceDeltasService) {}
  checkAdvantageModifiersFromAbilities(
    version: Pick<CharacterVersion, 'abilities'> | null | undefined,
    rules: Rule[],
    query?: CheckAdvantageQuery,
  ): AdvantageModifier[] {
    if (!version || query?.kind !== 'check') return [];
    const entries: AdvantageModifier[] = [];
    for (const ability of version.abilities) {
      if (ability.level < 1) continue;
      const rule = rules.find((entry) => entry.id === ability.ruleId);
      if (!rule || rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;
      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          if (grant.type !== 'check_advantage') continue;
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== ability.level) continue;
          if (!grant.check_codes.includes(query.code) || grant.amount === 0) continue;
          entries.push({
            source_code: grant.source_code ?? rule.code,
            source_label: rule.name,
            delta: grant.amount,
          });
        }
      }
    }

    return this.aggregate.aggregateSourceDeltas(entries);
  }

  checkCharacteristicModifiersFromAbilities(
    version: Pick<CharacterVersion, 'abilities'> | null | undefined,
    rules: Rule[],
    checkCode: string,
    characteristicCode: string,
  ): AdvantageModifier[] {
    if (!version) return [];
    const entries: AdvantageModifier[] = [];
    for (const ability of version.abilities) {
      if (ability.level < 1) continue;
      const rule = rules.find((entry) => entry.id === ability.ruleId);
      if (!rule || rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;
      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          if (
            grant.type !== 'characteristic_modify' ||
            grant.characteristic_code !== characteristicCode ||
            !grant.check_codes?.includes(checkCode)
          ) {
            continue;
          }
          if (grant.permanent === false && entry.level !== ability.level) continue;
          const delta = this.formulaValue(grant.amount, ability.parameters, version.abilities, rules);
          if (delta === 0) continue;
          entries.push({
            source_code: grant.source_code ?? rule.code,
            source_label: rule.name,
            delta,
          });
        }
      }
    }

    return this.aggregate.aggregateSourceDeltas(entries);
  }

  private formulaValue(
    formula: Formula,
    parameters: CharacterVersion['abilities'][number]['parameters'],
    abilities: CharacterVersion['abilities'],
    rules: Rule[],
  ): number {
    if (formula.type === 'fixed') return formula.value;
    if (formula.type === 'parameter') {
      const raw = parameters?.[formula.parameter_code];
      const value = typeof raw === 'number' ? raw : (raw?.base ?? 0);

      return value * formula.per_unit;
    }
    if (formula.type === 'parameter_floor_div') {
      const raw = parameters?.[formula.parameter_code];
      const value = typeof raw === 'number' ? raw : (raw?.base ?? 0);

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
