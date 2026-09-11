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
import { CHECK_SPELL_CAST_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';

/** Бросок проверки сотворения против посчитанной сложности. */
export class SpellCastService {
  rollForSpell(
    input: SpellCastResolveInput,
    checkCode: string | null,
    characteristicValue: DimensionalNumberValue,
    characteristicName: string,
    actorKey: CombatEntityKey | undefined,
    rng: DiceRng,
    rules: Rule[],
    mechanics: Mechanic[],
    advantages: AdvantageModifier[] = [],
  ): SpellCastRollOutcome {
    const computed = spellCastDifficultyService.computeForSpell(input, rules);
    if (!computed.needsCheck || !checkCode) {
      return { difficulty: computed.difficulty, needsCheck: false, roll: null };
    }
    const spec = characteristicRollService.characteristicRollSpec(
      {
        name: characteristicName,
        value: characteristicValue,
        actorKey,
        advantages,
      },
      rules,
    );
    const roll = checkRollService.rollNamedCheck(
      spec,
      CHECK_SPELL_CAST_CODE,
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
