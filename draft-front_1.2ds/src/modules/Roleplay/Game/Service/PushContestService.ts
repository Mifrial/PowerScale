import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { pushMathService } from '@/modules/Roleplay/Game/Service/Instance/pushMathService';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';

/** Совместная или соло проверка Мастерства ближнего боя для толчка. */
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
    checkCode?: string;
    attackerOverview: CharacterOverview | null;
    defenderOverview: CharacterOverview | null;
    attackerVersion: CharacterVersion | null | undefined;
    defenderVersion: CharacterVersion | null | undefined;
    attackerKey?: CombatEntityKey;
    defenderKey?: CombatEntityKey;
    attackerAdv?: number;
    defenderAdv?: number;
    rng: DiceRng;
    rules: Rule[];
    mechanics: Mechanic[];
  }): {
    attacker: DiceRollResult;
    defender: DiceRollResult | null;
    successRating: number;
  } {
    const checkCode = input.checkCode ?? 'check-push';
    const attackerPool = this.characteristicOf(
      input.attackerOverview,
      input.rules,
      'melee-combat',
      input.attackerVersion,
    );
    const attackerSpec = checkRollService.namedCheckSpec(
      'Толчок',
      attackerPool,
      input.attackerAdv ?? 0,
      input.rules,
      input.attackerKey,
    );
    if (input.reaction === 'block') {
      const joint = checkRollService.rollJointCheck(
        attackerSpec,
        checkRollService.namedCheckSpec(
          'Толчок (защита)',
          this.characteristicOf(input.defenderOverview, input.rules, 'melee-combat', input.defenderVersion),
          input.defenderAdv ?? 0,
          input.rules,
          input.defenderKey,
        ),
        checkCode,
        input.rng,
        input.rules,
        input.mechanics,
      );
      const attackerSuccesses = checkRollService.successesOf(joint.left);
      const defenderSuccesses = checkRollService.successesOf(joint.right);
      const rolledRating = pushMathService.successRating(attackerSuccesses, defenderSuccesses);
      const successRating = Math.max(rolledRating, 1);
      const leftCheck = joint.left.check;

      return {
        attacker: {
          ...joint.left,
          check: leftCheck
            ? { ...leftCheck, rating: successRating, passed: successRating > 0 }
            : {
                check_code: checkCode,
                difficulty: defenderSuccesses,
                passed: successRating > 0,
                rating: successRating,
              },
        },
        defender: joint.right,
        successRating,
      };
    }
    const attacker = checkRollService.rollNamedCheck(
      attackerSpec,
      checkCode,
      { base: 0, size: 0 },
      input.rng,
      input.rules,
      input.mechanics,
    );
    const successRating = Math.max(0, attacker.check?.rating ?? 0);

    return {
      attacker: {
        ...attacker,
        check: attacker.check
          ? { ...attacker.check, rating: successRating, passed: successRating > 0 }
          : {
              check_code: checkCode,
              difficulty: { base: 0, size: 0 },
              passed: successRating > 0,
              rating: successRating,
            },
      },
      defender: null,
      successRating,
    };
  }
}
