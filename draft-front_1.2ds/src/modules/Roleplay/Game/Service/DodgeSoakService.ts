import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { DodgeSoakCuts } from '@/modules/Roleplay/Game/Dto/DodgeSoakCuts';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/init';
import { CHARACTERISTIC_DEXTERITY_CODE } from '@/modules/Roleplay/Rule/Constant/Characteristic/CHARACTERISTIC_DEXTERITY_CODE';
import { CHARACTERISTIC_REACTION_CODE } from '@/modules/Roleplay/Rule/Constant/Characteristic/CHARACTERISTIC_REACTION_CODE';

/** Смягчение уклона: S0 = Ловкость.modify(−3), карты режут S, не кубы. */
export class DodgeSoakService {
  characteristicValue(
    overview: CharacterOverview | null | undefined,
    rules: Rule[],
    code: string,
  ): DimensionalNumberValue | null {
    if (!overview) return null;
    for (const characteristic of overview.characteristics ?? []) {
      if (rules.find((rule) => rule.code === characteristic.ruleCode)?.code === code) {
        return characteristic.value;
      }
    }

    return null;
  }

  baseSoakValue(dexterity: DimensionalNumberValue | null): DimensionalNumberValue | null {
    if (!dexterity) return null;

    return new DimensionalNumber(dexterity).modify(-3, CHARACTERISTIC_BASE_RANGE).value;
  }

  applyCuts(base: DimensionalNumberValue | null, cuts: DodgeSoakCuts, sr: number): number {
    if (!base) return 0;
    let soak = new DimensionalNumber(base);
    const sizeDelta = cuts.sizeDelta ?? 0;
    if (sizeDelta) soak = soak.modify(sizeDelta, CHARACTERISTIC_BASE_RANGE);
    let amount = Math.max(0, soak.toNumber() - Math.max(0, cuts.subtract ?? 0));
    const ignoreAtSr = cuts.ignoreAtSr;
    if (ignoreAtSr != null && sr >= ignoreAtSr) return 0;

    return amount;
  }

  amount(input: {
    reaction: HitDefenseReaction;
    defenderOverview: CharacterOverview | null | undefined;
    rules: Rule[];
    sr: number;
    cuts?: DodgeSoakCuts;
  }): number {
    if (input.reaction !== 'dodge') return 0;
    const dexterity = this.characteristicValue(input.defenderOverview, input.rules, CHARACTERISTIC_DEXTERITY_CODE);

    return this.applyCuts(this.baseSoakValue(dexterity), input.cuts ?? {}, input.sr);
  }

  reactionToNumber(overview: CharacterOverview | null | undefined, rules: Rule[]): number {
    const value = this.characteristicValue(overview, rules, CHARACTERISTIC_REACTION_CODE);
    if (!value) return 0;

    return Math.max(0, new DimensionalNumber(value).toNumber());
  }
}
