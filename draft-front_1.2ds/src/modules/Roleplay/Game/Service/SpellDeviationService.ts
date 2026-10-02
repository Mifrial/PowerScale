import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { SpellCastRollOutcome } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastRollOutcome';
import type { SpellDeviationOutcome } from '@/modules/Roleplay/Game/Dto/Spell/SpellDeviationOutcome';
import { SPELL_DEVIATION_NEUTRAL_SUM } from '@/modules/Roleplay/Game/Constant/Spell/SPELL_DEVIATION_NEUTRAL_SUM';
import { MAGIC_POWER_SMALL_SIZE } from '@/modules/Roleplay/Game/Constant/Spell/MAGIC_POWER_SMALL_SIZE';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

/** 2d6 после провала check, состояние на ядре и урон арканного взрыва. */
export class SpellDeviationService {
  isFailedCastCheck(cast: SpellCastRollOutcome | null | undefined): boolean {
    return cast?.roll?.check?.passed === false;
  }

  roll(rng: DiceRng, actorKey: CombatEntityKey | undefined): SpellDeviationOutcome {
    const firstFace = this.rollFace(rng);
    const secondFace = this.rollFace(rng);
    const faceSum = firstFace + secondFace;
    const strength = faceSum - SPELL_DEVIATION_NEUTRAL_SUM;
    const isDoubles = firstFace === secondFace;

    return {
      firstFace,
      secondFace,
      faceSum,
      strength,
      hasEffect: strength > 0,
      isDoubles,
      dieDigit: firstFace,
      roll: {
        spec: {
          diceCount: 2,
          dieSize: 0,
          dieFaces: 6,
          efficiency: 6,
          scoring: 'face_sum',
          advantages: [],
          label: 'Магическое отклонение',
          actorKey,
        },
        rolls: [firstFace, secondFace],
        successes: [0, 0],
        adjustedRolls: [firstFace, secondFace],
        droppedRolls: [],
        totalSuccesses: 0,
        faceSum,
        deviationStrength: strength,
      },
    };
  }

  boundIndex(states: CharacterStateValue[], sourceKey: string, rules: Rule[]): number {
    const code = attackDamageService.magicDeviationRule(rules)?.code;
    if (!sourceKey || !code) {
      return -1;
    }

    return states.findIndex((state) => state.stateRuleCode === code && state.boundSourceKey === sourceKey);
  }

  strengthOnSource(states: CharacterStateValue[], sourceKey: string, rules: Rule[]): number {
    const code = attackDamageService.magicDeviationRule(rules)?.code;
    if (!sourceKey || !code) {
      return 0;
    }

    return states.reduce((sum, state) => {
      if (state.stateRuleCode !== code || state.boundSourceKey !== sourceKey) {
        return sum;
      }

      return sum + Math.max(0, state.value ?? 0);
    }, 0);
  }

  grant(states: CharacterStateValue[], sourceKey: string, strength: number, rules: Rule[]): CharacterStateValue | null {
    const code = attackDamageService.magicDeviationRule(rules)?.code;
    if (!sourceKey || strength <= 0 || !code) {
      return null;
    }
    const index = this.boundIndex(states, sourceKey, rules);
    const current = index >= 0 ? (states[index]?.value ?? 0) : 0;

    return {
      stateRuleCode: code,
      value: current + strength,
      boundSourceKey: sourceKey,
    };
  }

  decay(state: CharacterStateValue, rules: Rule[]): CharacterStateValue | null {
    const code = attackDamageService.magicDeviationRule(rules)?.code;
    if (!code || state.stateRuleCode !== code) {
      return state;
    }
    const next = Math.max(0, (state.value ?? 0) - 1);
    if (next <= 0) {
      return null;
    }

    return { ...state, value: next };
  }

  decayPatches(states: CharacterStateValue[], rules: Rule[]): { index: number; next: CharacterStateValue | null }[] {
    const code = attackDamageService.magicDeviationRule(rules)?.code;
    if (!code) return [];
    const patches: { index: number; next: CharacterStateValue | null }[] = [];
    for (const [index, state] of states.entries()) {
      if (state.stateRuleCode !== code) {
        continue;
      }
      patches.push({ index, next: this.decay(state, rules) });
    }

    return patches;
  }

  penalizeUsedPower(usedPower: DimensionalNumberValue, strength: number): DimensionalNumberValue {
    if (strength <= 0) {
      return usedPower;
    }
    const next = CharacteristicNumber.from(usedPower).modifyWith(-strength);
    if (next.value.size < MAGIC_POWER_SMALL_SIZE) {
      return { base: 0, size: 0 };
    }

    return next.value;
  }

  explosionAmount(
    usedPower: DimensionalNumberValue,
    distanceIpari: number,
    dieDigit: number,
    grantResistance: number,
  ): number {
    const powerN = DimensionalNumber.from(usedPower).toNumber();
    const formula = Math.max(0, (powerN - Math.max(0, distanceIpari)) * dieDigit);

    return Math.max(0, formula - Math.max(0, grantResistance));
  }

  explosionWeaponDamage(amount: number): DimensionalNumberValue {
    return { base: Math.max(0, amount), size: 0 };
  }

  formatOutcomeMessage(outcome: SpellDeviationOutcome): string {
    if (outcome.hasEffect) {
      return `Магическое отклонение силой ${outcome.strength}.`;
    }

    return 'Магического отклонения нет.';
  }

  formatBurstBeginMessage(outcome: SpellDeviationOutcome): string | null {
    if (!outcome.isDoubles) {
      return null;
    }

    return 'Собранная магия взрывается.';
  }

  private rollFace(rng: DiceRng): number {
    return Math.min(6, Math.max(1, Math.floor(rng() * 6) + 1));
  }
}
