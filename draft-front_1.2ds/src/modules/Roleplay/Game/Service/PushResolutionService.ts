import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { HitCheckRoll } from '@/modules/Roleplay/Game/Dto/HitCheckRoll';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CheckOfferProposal } from '@/modules/Roleplay/Game/Dto/CheckOfferProposal';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { PushResolution } from '@/modules/Roleplay/Game/Dto/PushResolution';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { findRuleByRef } from '@/modules/Roleplay/Game/Utils/combatActions';
import { pushContestService } from '@/modules/Roleplay/Game/Service/Instance/pushContestService';
import { pushMathService } from '@/modules/Roleplay/Game/Service/Instance/pushMathService';
import { pushProfileService } from '@/modules/Roleplay/Game/Service/Instance/pushProfileService';

/** Собрать исход толчка: проверка, урон, Устойчивость, Неустойчивость и отброс. */
export class PushResolutionService {
  prepare(input: {
    hit: NonNullable<CheckOfferProposal['hit']>;
    attack: AttackOverview;
    meleeRolled: HitCheckRoll | null;
    attackerKey: CombatEntityKey;
    defenderKey: CombatEntityKey;
    attackerOverview: CharacterOverview | null;
    defenderOverview: CharacterOverview | null;
    attackerVersion: CharacterVersion | null | undefined;
    defenderVersion: CharacterVersion | null | undefined;
    rng: DiceRng;
    rules: Rule[];
    mechanics: Mechanic[];
  }): PushResolution {
    if (input.hit.reaction === 'dodge' && input.meleeRolled) {
      if ((input.meleeRolled.attacker.check?.rating ?? 0) <= 0) {
        return { kind: 'dodge_miss', rolled: input.meleeRolled };
      }
    }

    const push = pushProfileService.pushOf(findRuleByRef(input.rules, input.hit.actionRuleCode));
    let rating: number;
    let rolled: HitCheckRoll;
    if (input.hit.reaction === 'dodge' && input.meleeRolled) {
      rating = Math.max(0, input.meleeRolled.attacker.check?.rating ?? 0);
      rolled = { attacker: input.meleeRolled.attacker, defender: input.meleeRolled.defender };
    } else {
      const contest = pushContestService.resolve({
        reaction: input.hit.reaction === 'block' ? 'block' : 'ignore',
        checkCode: push?.check,
        attackerOverview: input.attackerOverview,
        defenderOverview: input.defenderOverview,
        attackerVersion: input.attackerVersion,
        defenderVersion: input.defenderVersion,
        attackerKey: input.attackerKey,
        defenderKey: input.defenderKey,
        rng: input.rng,
        rules: input.rules,
        mechanics: input.mechanics,
      });
      rating = contest.successRating;
      rolled = { attacker: contest.attacker, defender: contest.defender };
    }

    const attackerStrength = pushContestService.characteristicOf(
      input.attackerOverview,
      input.rules,
      'strength',
      input.attackerVersion,
    );
    const defenderStrength = pushContestService.characteristicOf(
      input.defenderOverview,
      input.rules,
      'strength',
      input.defenderVersion,
    );
    const weight = pushContestService.characteristicOf(
      input.defenderOverview,
      input.rules,
      'weight',
      input.defenderVersion,
    );
    const floor = pushMathService.stabilityFloor(weight);
    const stability = pushMathService.stability(
      input.hit.reaction,
      rating,
      defenderStrength,
      floor,
      push?.stability_bonus_cap ?? 6,
    );
    const divisor = input.attack.damageTypeCode
      ? (push?.posture_rating_divisor_by_damage_type?.[input.attack.damageTypeCode] ?? 1)
      : 1;
    const unstable = pushMathService.unstable(attackerStrength, stability, divisor);
    const damage = pushMathService.damageModifier(input.attack.damage, push?.damage_modifier ?? -3);
    const attack: AttackOverview = {
      ...input.attack,
      damage,
      damageLabel: new DimensionalNumber(damage).toString(),
    };
    const lyingCode = attackDamageService.lyingRule(input.rules)?.code;
    const lying = Boolean(
      lyingCode && input.defenderVersion?.states.some((state) => state.stateRuleCode === lyingCode),
    );
    const meleeRolls =
      input.hit.reaction === 'dodge' && input.meleeRolled
        ? input.meleeRolled.defender
          ? [input.meleeRolled.attacker, input.meleeRolled.defender]
          : [input.meleeRolled.attacker]
        : null;

    return {
      kind: 'contest',
      meleeRolls,
      rolled,
      attack,
      applySr: rating,
      skipDamageApply: rating <= 0,
      knockbackIpari: pushMathService.knockbackIpari(unstable),
      unstableAmount: unstable > 0 && !lying ? unstable : null,
      unstableSkippedBecauseLying: unstable > 0 && lying,
    };
  }
}
