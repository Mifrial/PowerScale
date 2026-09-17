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
import { BLUNT_DAMAGE_TYPE_CODE } from '@/modules/Roleplay/Rule/Constant/DamageType/BLUNT_DAMAGE_TYPE_CODE';
import { LYING_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { findRuleByRef } from '@/modules/Roleplay/Game/Utils/combatActions';
import { pushContestService } from '@/modules/Roleplay/Game/Service/Instance/pushContestService';
import { pushMathService } from '@/modules/Roleplay/Game/Service/Instance/pushMathService';
import { pushProfileService } from '@/modules/Roleplay/Game/Service/Instance/pushProfileService';

/** Собрать исход толчка: уклонение, контест, урон, отброс и Неустойчивость. */
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
    const contest = pushContestService.resolve({
      reaction: input.hit.reaction === 'block' ? 'block' : 'ignore',
      attackerOverview: input.attackerOverview,
      defenderOverview: input.defenderOverview,
      attackerVersion: input.attackerVersion,
      defenderVersion: input.defenderVersion,
      attackerKey: input.attackerKey,
      defenderKey: input.defenderKey,
      attackerPool: push?.pool === 'weapon_damage' ? input.attack.damage : undefined,
      rng: input.rng,
      rules: input.rules,
      mechanics: input.mechanics,
    });
    const weaponTimesSr = push?.damage === 'weapon_times_sr';
    const attack: AttackOverview = weaponTimesSr
      ? input.attack
      : {
          ...input.attack,
          damage: contest.damage,
          damageTypeCode: BLUNT_DAMAGE_TYPE_CODE,
          damageLabel: new DimensionalNumber(contest.damage).toString(),
        };
    const postureDivisor = input.attack.damageTypeCode
      ? (push?.posture_rating_divisor_by_damage_type?.[input.attack.damageTypeCode] ?? 1)
      : 1;
    const postureRating = pushMathService.postureRating(contest.successRating, postureDivisor);
    const lying = Boolean(input.defenderVersion?.states.some((state) => state.stateRuleCode === LYING_STATE_CODE));
    const meleeRolls =
      input.hit.reaction === 'dodge' && input.meleeRolled
        ? input.meleeRolled.defender
          ? [input.meleeRolled.attacker, input.meleeRolled.defender]
          : [input.meleeRolled.attacker]
        : null;

    return {
      kind: 'contest',
      meleeRolls,
      rolled: { attacker: contest.attacker, defender: contest.defender },
      attack,
      applySr: weaponTimesSr ? contest.successRating : contest.successRating > 0 ? 1 : 0,
      skipDamageApply: contest.successRating <= 0,
      knockbackIpari: postureRating > 0 ? pushMathService.knockbackIpari(postureRating) : null,
      unstableAmount: postureRating > 0 && !lying ? postureRating : null,
      unstableSkippedBecauseLying: postureRating > 0 && lying,
    };
  }
}
