import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CoverInvite } from '@/modules/Roleplay/Game/Dto/CoverInvite';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import { ADVANTAGE_SOURCE_CIRCUMSTANCES } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { COVERING_ABILITY_CODE } from '@/modules/Roleplay/Game/Constant/Combat/COVERING_ABILITY_CODE';
import { COVERING_ACTION_COST } from '@/modules/Roleplay/Game/Constant/Combat/COVERING_ACTION_COST';

/** Альтернативные защитники одной реакции: Прикрытие. */
export class CoveringService {
  cost(): number {
    return COVERING_ACTION_COST;
  }

  hasSkill(abilities: CharacterAbility[] | undefined): boolean {
    return (abilities?.find((ability) => ability.ruleCode === COVERING_ABILITY_CODE)?.level ?? 0) >= 1;
  }

  canUseReaction(reaction: HitDefenseReaction | null | undefined): boolean {
    return reaction === 'block';
  }

  eligibleKeys(
    keys: CombatEntityKey[],
    attackerKey: CombatEntityKey | null,
    defenderKeys: CombatEntityKey[],
    abilitiesOf: (key: CombatEntityKey) => CharacterAbility[] | undefined,
  ): CombatEntityKey[] {
    return keys.filter((key) => {
      if (key === attackerKey || defenderKeys.includes(key)) return false;

      return this.hasSkill(abilitiesOf(key));
    });
  }

  pendingInvites(invites: CoverInvite[] | undefined): CoverInvite[] {
    return (invites ?? []).filter((invite) => invite.decision === 'pending');
  }

  acceptedInvites(invites: CoverInvite[] | undefined): CoverInvite[] {
    return (invites ?? []).filter((invite) => invite.decision === 'cover');
  }

  circumstanceModifier(): AdvantageModifier {
    return {
      source_code: ADVANTAGE_SOURCE_CIRCUMSTANCES,
      source_label: 'Обстоятельства',
      delta: -1,
    };
  }

  bestPassedKey(
    results: { key: CombatEntityKey; passed: boolean; rating: number }[],
  ): CombatEntityKey | null {
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
}
