import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { pushMathService } from '@/modules/Roleplay/Game/Service/Instance/pushMathService';
import { CHECK_STRENGTH_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_STRENGTH_CODE';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';

/** Совместная или соло проверка Силы для толчка. */
export class PushContestService {
  characteristicOf(
    overview: CharacterOverview | null,
    rules: Rule[],
    code: string,
    version: CharacterVersion | null | undefined,
  ): DimensionalNumberValue {
    const fromOverview = overview?.characteristics.find(
      (item) => rules.find((rule) => rule.code === item.ruleCode)?.code === code,
    );
    if (fromOverview) return fromOverview.value;
    const fromState = version
      ? stateRuntimeEffectsService.effectiveCharacteristicValues(version, rules).get(code)
      : undefined;

    return fromState ?? { base: 3, size: 0 };
  }

  resolve(input: {
    reaction: HitDefenseReaction;
    attackerOverview: CharacterOverview | null;
    defenderOverview: CharacterOverview | null;
    attackerVersion: CharacterVersion | null | undefined;
    defenderVersion: CharacterVersion | null | undefined;
    attackerKey?: CombatEntityKey;
    defenderKey?: CombatEntityKey;
    attackerAdv?: number;
    defenderAdv?: number;
    attackerPool?: DimensionalNumberValue;
    rng: DiceRng;
    rules: Rule[];
    mechanics: Mechanic[];
  }): {
    attacker: DiceRollResult;
    defender: DiceRollResult | null;
    successRating: number;
    damage: DimensionalNumberValue;
    knockbackIpari: DimensionalNumberValue;
  } {
    const floor = pushMathService.pushFloor(
      this.characteristicOf(input.defenderOverview, input.rules, 'weight', input.defenderVersion),
    );
    const attackerPool =
      input.attackerPool ?? this.characteristicOf(input.attackerOverview, input.rules, 'strength', input.attackerVersion);
    const attackerSpec = checkRollService.namedCheckSpec(
      input.attackerPool ? 'Урон (толчок)' : 'Сила (толчок)',
      attackerPool,
      input.attackerAdv ?? 0,
      input.rules,
      input.attackerKey,
    );
    if (input.reaction === 'block') {
      const joint = checkRollService.rollJointCheck(
        attackerSpec,
        checkRollService.namedCheckSpec(
          'Сила (толчок, защита)',
          this.characteristicOf(input.defenderOverview, input.rules, 'strength', input.defenderVersion),
          input.defenderAdv ?? 0,
          input.rules,
          input.defenderKey,
        ),
        CHECK_STRENGTH_CODE,
        input.rng,
        input.rules,
        input.mechanics,
      );
      const defenderSuccesses = pushMathService.raiseDefenderSuccesses(
        checkRollService.successesOf(joint.right),
        floor,
      );
      const attackerSuccesses = checkRollService.successesOf(joint.left);
      const successRating = pushMathService.successRating(attackerSuccesses, defenderSuccesses);
      const leftCheck = joint.left.check;

      return {
        attacker: {
          ...joint.left,
          check: leftCheck
            ? { ...leftCheck, rating: successRating, passed: successRating > 0 }
            : {
                check_code: CHECK_STRENGTH_CODE,
                difficulty: defenderSuccesses,
                passed: successRating > 0,
                rating: successRating,
              },
        },
        defender: joint.right,
        successRating,
        damage: pushMathService.crushingDamage(attackerSuccesses),
        knockbackIpari: pushMathService.knockbackIpari(successRating),
      };
    }
    const attacker = checkRollService.rollNamedCheck(
      attackerSpec,
      CHECK_STRENGTH_CODE,
      floor,
      input.rng,
      input.rules,
      input.mechanics,
    );
    const attackerSuccesses = checkRollService.successesOf(attacker);
    const successRating = Math.max(0, attacker.check?.rating ?? pushMathService.successRating(attackerSuccesses, floor));
    const strengthCheck = attacker.check;

    return {
      attacker: {
        ...attacker,
        check: strengthCheck
          ? { ...strengthCheck, rating: successRating, passed: successRating > 0 }
          : {
              check_code: CHECK_STRENGTH_CODE,
              difficulty: floor,
              passed: successRating > 0,
              rating: successRating,
            },
      },
      defender: null,
      successRating,
      damage: pushMathService.crushingDamage(attackerSuccesses),
      knockbackIpari: pushMathService.knockbackIpari(successRating),
    };
  }
}
