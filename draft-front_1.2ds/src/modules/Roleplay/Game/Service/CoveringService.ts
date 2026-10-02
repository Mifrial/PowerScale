import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CoverInvite } from '@/modules/Roleplay/Game/Dto/CoverInvite';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { ADVANTAGE_SOURCE_CIRCUMSTANCES } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { actionOdCost, asActionAbilitySpec, turnResourceCode } from '@/modules/Roleplay/Game/Utils/combatActions';

/** Альтернативные защитники одной реакции: Прикрытие. */
export class CoveringService {
  cost(rules: Rule[]): number {
    return actionOdCost(asActionAbilitySpec(this.coverRule(rules))?.action_components, 0, turnResourceCode(rules));
  }

  hasSkill(abilities: CharacterAbility[] | undefined, rules: Rule[]): boolean {
    return (abilities ?? []).some((ability) => ability.level > 0 && this.coverRule(rules, ability.ruleCode));
  }

  canUseReaction(reaction: HitDefenseReaction | null | undefined): boolean {
    return reaction === 'block';
  }

  eligibleKeys(
    keys: CombatEntityKey[],
    attackerKey: CombatEntityKey | null,
    defenderKeys: CombatEntityKey[],
    abilitiesOf: (key: CombatEntityKey) => CharacterAbility[] | undefined,
    rules: Rule[],
  ): CombatEntityKey[] {
    return keys.filter((key) => {
      if (key === attackerKey || defenderKeys.includes(key)) return false;

      return this.hasSkill(abilitiesOf(key), rules);
    });
  }

  pendingInvites(invites: CoverInvite[] | undefined): CoverInvite[] {
    return (invites ?? []).filter((invite) => invite.decision === 'pending');
  }

  acceptedInvites(invites: CoverInvite[] | undefined): CoverInvite[] {
    return (invites ?? []).filter((invite) => invite.decision === 'cover');
  }

  circumstanceModifier(rules: Rule[]): AdvantageModifier {
    const spec = this.coverRule(rules)?.spec;

    return {
      source_code: ADVANTAGE_SOURCE_CIRCUMSTANCES,
      source_label: 'Обстоятельства',
      delta: spec && typeof spec === 'object' && 'cover_ally' in spec ? (spec.cover_ally?.circumstance_delta ?? 0) : 0,
    };
  }

  bestPassedKey(results: { key: CombatEntityKey; passed: boolean; rating: number }[]): CombatEntityKey | null {
    const passed = results.filter((result) => result.passed);
    if (passed.length === 0) return null;

    return passed.reduce((best, result) => (result.rating > best.rating ? result : best)).key;
  }

  failChoiceKeys(primaryKey: CombatEntityKey, coveringKeys: CombatEntityKey[]): CombatEntityKey[] {
    return [primaryKey, ...coveringKeys.filter((key) => key !== primaryKey)];
  }

  actualTarget(input: {
    results: { key: CombatEntityKey; passed: boolean; rating: number }[];
    primaryKey: CombatEntityKey;
    attackerChoice: CombatEntityKey | null | undefined;
  }): CombatEntityKey | null {
    const coveringKeys = input.results.map((result) => result.key);
    if (coveringKeys.length === 0) return input.primaryKey;
    const winner = this.bestPassedKey(input.results);
    if (winner) return winner;
    if (input.attackerChoice && this.failChoiceKeys(input.primaryKey, coveringKeys).includes(input.attackerChoice)) {
      return input.attackerChoice;
    }

    return null;
  }

  private coverRule(rules: Rule[], ruleCode?: string): Rule | null {
    return (
      rules.find((rule) => {
        if (rule.type !== 'ability' || (ruleCode !== undefined && rule.code !== ruleCode)) return false;
        const spec = rule.spec;

        return Boolean(spec && typeof spec === 'object' && 'cover_ally' in spec && spec.cover_ally);
      }) ?? null
    );
  }
}
