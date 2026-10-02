import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { SpellCastResolveInput } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastResolveInput';
import type { SpellCastRollOutcome } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastRollOutcome';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characteristicRollService } from '@/modules/Roleplay/Game/Service/Instance/characteristicRollService';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';
import { spellCastEfficiencyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastEfficiencyService';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';

/** Бросок проверки сотворения против посчитанной сложности. */
export class SpellCastService {
  rollForSpell(
    input: SpellCastResolveInput,
    _checkCode: string | null,
    castCheckCode: string | null,
    characteristicValue: DimensionalNumberValue,
    characteristicName: string,
    actorKey: CombatEntityKey | undefined,
    rng: DiceRng,
    rules: Rule[],
    mechanics: Mechanic[],
    advantages: AdvantageModifier[] = [],
    efficiencyDeltas: readonly { sourceCode: string; delta: number }[] = [],
  ): SpellCastRollOutcome {
    const computed = spellCastDifficultyService.computeForSpell(input, rules);
    if (!computed.needsCheck || !castCheckCode) {
      return { difficulty: computed.difficulty, needsCheck: false, roll: null };
    }
    const baseSpec = characteristicRollService.characteristicRollSpec(
      {
        name: characteristicName,
        value: characteristicValue,
        actorKey,
        advantages,
      },
      rules,
    );
    const spec = spellCastEfficiencyService.apply(baseSpec, efficiencyDeltas);
    const roll = checkRollService.rollNamedCheck(
      spec,
      castCheckCode,
      computed.difficulty,
      rng,
      rules,
      mechanics,
    );

    return { difficulty: computed.difficulty, needsCheck: true, roll };
  }

  characteristicCodeForCheck(checkCode: string | null, rules: Rule[]): string | null {
    if (!checkCode) {
      return null;
    }

    return checkResolutionService.resolveCheckCharacteristicCode(checkCode, rules);
  }
}
