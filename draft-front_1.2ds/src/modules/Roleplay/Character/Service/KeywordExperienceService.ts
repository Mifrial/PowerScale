import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Сумма базовых стоимостей взятых способностей с keyword (опыт школы / derived_level).
 */
export class KeywordExperienceService {
  experienceOf(
    sourceKeyword: string,
    abilities: CharacterAbility[],
    rules: Rule[],
    keywords: Keyword[],
    costOf: (ability: CharacterAbility, rule: Rule, spec: Exclude<AbilitySpec, { type: 'group' }>) => number,
  ): number {
    const keywordId = keywords.find((keyword) => keyword.code === sourceKeyword)?.id;
    if (keywordId === undefined) {
      return 0;
    }
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    let sum = 0;
    for (const ability of abilities) {
      if (ability.level < 1) {
        continue;
      }
      const rule = byCode.get(ability.ruleCode);
      if (!rule || !(rule.keywordIds ?? []).includes(keywordId)) {
        continue;
      }
      const spec = this.asPricedSpec(rule);
      if (!spec) {
        continue;
      }
      sum += costOf(ability, rule, spec);
    }

    return sum;
  }

  zoneLadderCost(
    ability: CharacterAbility,
    spec: Exclude<AbilitySpec, { type: 'group' }>,
    resolveParameter?: (code: string) => number,
  ): number {
    const zoneCode = ability.zone ?? this.purchasableZoneOf(spec);
    if (!zoneCode) {
      return 0;
    }
    const cost = spec.zones[zoneCode];
    if (!cost) {
      return 0;
    }

    return this.totalCostAtLevel(cost, ability.level, resolveParameter);
  }

  purchasableZoneOf(spec: AbilitySpec): string | null {
    if (spec.type === 'group') {
      return null;
    }
    const zones = spec.zones as Partial<Record<string, AbilityCost>> | undefined;
    const purchasable = Object.entries(zones ?? {})
      .filter(([, cost]) => cost && cost.kind !== 'automatic')
      .map(([zoneCode]) => zoneCode);

    return purchasable.length > 0 ? purchasable[0] : null;
  }

  totalCostAtLevel(cost: AbilityCost, level: number, resolveParameter?: (code: string) => number): number {
    if (level <= 0) {
      return 0;
    }
    const costs = this.levelCosts(cost, resolveParameter);

    return costs.slice(0, Math.min(level, costs.length)).reduce((sum, value) => sum + value, 0);
  }

  levelCosts(cost: AbilityCost, resolveParameter?: (code: string) => number): number[] {
    switch (cost.kind) {
      case 'array':
        return [...cost.levels_cost];
      case 'progression': {
        const result: number[] = [];
        for (let level = 1; level <= cost.max_level; level++) {
          result.push(cost.base_cost + (level - 1) * cost.step);
        }

        return result;
      }
      case 'parameter': {
        const value = resolveParameter?.(cost.parameter_code) ?? 1;

        return [cost.per_unit * value];
      }
      case 'parameter_table': {
        const value = resolveParameter?.(cost.parameter_code) ?? 1;

        return [typeof value === 'number' ? value : (cost.costs[String(value)] ?? 0)];
      }
      case 'parameter_sum_tables': {
        let sum = 0;
        for (const [code, table] of Object.entries(cost.tables)) {
          const value = resolveParameter?.(code) ?? 0;
          sum += table[String(value)] ?? 0;
        }

        return [sum];
      }
      case 'automatic':
        return [0];
    }
  }

  private asPricedSpec(rule: Rule): Exclude<AbilitySpec, { type: 'group' }> | null {
    if (rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') {
      return null;
    }
    const spec = rule.spec as AbilitySpec;
    if (spec.type === 'group') {
      return null;
    }

    return spec;
  }
}
