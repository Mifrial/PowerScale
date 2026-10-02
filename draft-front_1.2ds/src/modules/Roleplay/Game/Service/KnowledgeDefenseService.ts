import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { ADVANTAGE_SOURCE_TRAINING } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';

/** Преимущество «Защиты знанием»: известна атакующая способность с уровнем ≥ 1. */
export class KnowledgeDefenseService {
  hasSkill(abilities: CharacterAbility[] | undefined, rules: Rule[]): boolean {
    return (abilities ?? []).some((ability) => ability.level > 0 && this.defenseRule(rules, ability.ruleCode));
  }

  knowsAttack(abilities: CharacterAbility[] | undefined, actionRuleCode: string | null | undefined): boolean {
    if (!actionRuleCode) return false;

    return this.levelOf(abilities, actionRuleCode) >= 1;
  }

  modifier(
    abilities: CharacterAbility[] | undefined,
    actionRuleCode: string | null | undefined,
    rules: Rule[],
  ): AdvantageModifier | null {
    const rule = (abilities ?? [])
      .filter((ability) => ability.level > 0)
      .map((ability) => this.defenseRule(rules, ability.ruleCode))
      .find((entry) => entry);
    const spec = rule?.spec;
    const field =
      spec && typeof spec === 'object' && 'known_attack_defense' in spec ? spec.known_attack_defense : undefined;
    if (!field || !this.knowsAttack(abilities, actionRuleCode)) return null;

    return {
      source_code: field.source_code ?? ADVANTAGE_SOURCE_TRAINING,
      source_label: 'тренировки',
      delta: field.delta,
    };
  }

  private defenseRule(rules: Rule[], ruleCode: string): Rule | null {
    return (
      rules.find((rule) => {
        if (rule.type !== 'ability' || rule.code !== ruleCode) return false;
        const spec = rule.spec;

        return Boolean(spec && typeof spec === 'object' && 'known_attack_defense' in spec && spec.known_attack_defense);
      }) ?? null
    );
  }

  private levelOf(abilities: CharacterAbility[] | undefined, ruleCode: string): number {
    return abilities?.find((ability) => ability.ruleCode === ruleCode)?.level ?? 0;
  }
}
