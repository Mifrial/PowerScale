import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { ADVANTAGE_SOURCE_TRAINING } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { KNOWLEDGE_DEFENSE_ABILITY_CODE } from '@/modules/Roleplay/Game/Constant/Combat/KNOWLEDGE_DEFENSE_ABILITY_CODE';

/** Преимущество «Защиты знанием»: известна атакующая способность с уровнем ≥ 1. */
export class KnowledgeDefenseService {
  hasSkill(abilities: CharacterAbility[] | undefined): boolean {
    return this.levelOf(abilities, KNOWLEDGE_DEFENSE_ABILITY_CODE) >= 1;
  }

  knowsAttack(abilities: CharacterAbility[] | undefined, actionRuleCode: string | null | undefined): boolean {
    if (!actionRuleCode) return false;

    return this.levelOf(abilities, actionRuleCode) >= 1;
  }

  modifier(
    abilities: CharacterAbility[] | undefined,
    actionRuleCode: string | null | undefined,
  ): AdvantageModifier | null {
    if (!this.hasSkill(abilities) || !this.knowsAttack(abilities, actionRuleCode)) return null;

    return {
      source_code: ADVANTAGE_SOURCE_TRAINING,
      source_label: 'тренировки',
      delta: 1,
    };
  }

  private levelOf(abilities: CharacterAbility[] | undefined, ruleCode: string): number {
    return abilities?.find((ability) => ability.ruleCode === ruleCode)?.level ?? 0;
  }
}
