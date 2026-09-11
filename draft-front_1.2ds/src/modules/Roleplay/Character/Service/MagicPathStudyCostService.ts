import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';
import type { MagicPathStudyCost } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathStudyCost';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Стоимость изучения заклинания в рамках пути: скидка из спеки пути и парные покупки
 * базовой цены 1 (два за одно очко у Арканиста).
 */
export class MagicPathStudyCostService {
  usesPathStudyCost(spec: AbilitySpec, rules: Rule[]): boolean {
    if (spec.type === 'spell') return true;
    if (spec.type === 'group') return false;
    if (spec.domain_ref === 'magic-path') return true;
    if (!spec.parent_ability_code) return false;
    const parent = rules.find((entry) => entry.code === spec.parent_ability_code && entry.type === 'ability');
    const parentSpec = parent?.spec as AbilitySpec | undefined;

    return parentSpec?.type === 'spell';
  }

  discounted(base: number, studyCost: MagicPathStudyCost): number {
    return Math.max(0, base - Math.floor(base * studyCost.discount_fraction));
  }

  pathSpecOf(rules: Rule[], pathCode: string | null | undefined): MagicPathSpec | null {
    if (!pathCode) return null;
    const rule = rules.find((entry) => entry.code === pathCode && entry.type === 'magic_path');
    const spec = rule?.spec as MagicPathSpec | undefined;
    if (!spec || spec.type !== 'magic_path') return null;

    return spec;
  }

  instancePaid(
    build: CharacterBuild,
    rules: Rule[],
    ability: CharacterAbility,
    basePaid: number,
    firstLevelCost: number,
    skip: (ability: CharacterAbility) => boolean = () => false,
  ): number {
    const spec = this.pathSpecOf(rules, ability.domainCode);
    const studyCost = spec?.study_cost;
    if (!studyCost || ability.level < 1) return basePaid;
    if (studyCost.pair_base_cost != null && firstLevelCost === studyCost.pair_base_cost) {
      const index = this.pairIndex(build, rules, ability, studyCost.pair_base_cost, firstLevelCost, skip);

      return index % 2 === 0 ? this.discounted(firstLevelCost, studyCost) : 0;
    }

    return this.discounted(basePaid, studyCost);
  }

  nextInstanceCost(
    build: CharacterBuild,
    rules: Rule[],
    pathCode: string | null,
    firstLevelCost: number,
    skip: (ability: CharacterAbility) => boolean = () => false,
  ): number {
    const spec = this.pathSpecOf(rules, pathCode);
    const studyCost = spec?.study_cost;
    if (!studyCost) return firstLevelCost;
    if (studyCost.pair_base_cost != null && firstLevelCost === studyCost.pair_base_cost) {
      const count = this.pairGroup(build, rules, pathCode, studyCost.pair_base_cost, skip).length;

      return count % 2 === 0 ? this.discounted(firstLevelCost, studyCost) : 0;
    }

    return this.discounted(firstLevelCost, studyCost);
  }

  private pairIndex(
    build: CharacterBuild,
    rules: Rule[],
    ability: CharacterAbility,
    pairBase: number,
    firstLevelCost: number,
    skip: (ability: CharacterAbility) => boolean,
  ): number {
    const group = this.pairGroup(build, rules, ability.domainCode ?? null, pairBase, skip);
    const key = this.instanceKey(ability);

    return group.findIndex((entry) => this.instanceKey(entry) === key && firstLevelCost === pairBase);
  }

  private pairGroup(
    build: CharacterBuild,
    rules: Rule[],
    pathCode: string | null,
    pairBase: number,
    skip: (ability: CharacterAbility) => boolean,
  ): CharacterAbility[] {
    return build.abilities
      .filter((ability) => {
        if (ability.level < 1) return false;
        if (skip(ability)) return false;
        if ((ability.domainCode ?? null) !== pathCode) return false;
        const rule = rules.find((entry) => entry.code === ability.ruleCode);
        const spec = rule?.type === 'ability' ? (rule.spec as AbilitySpec | undefined) : undefined;
        if (!spec || spec.type === 'group' || !this.usesPathStudyCost(spec, rules)) return false;
        const zone = spec.zones.or ?? spec.zones.os;
        if (!zone || zone.kind !== 'array') return false;

        return (zone.levels_cost[0] ?? 0) === pairBase;
      })
      .sort((left, right) => this.instanceKey(left).localeCompare(this.instanceKey(right)));
  }

  private instanceKey(ability: CharacterAbility): string {
    return `${ability.ruleCode}:${ability.domainCode ?? ''}:${ability.domain ?? ''}`;
  }
}
