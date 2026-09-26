import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { CHECK_SPELL_CAST_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

/** Бонус эффективности проверки сотворения от навыков вроде «Свойства базовых элементов». */
export class SpellCastEfficiencyService {
  deltasForAbilities(abilities: readonly Pick<CharacterAbility, 'ruleCode' | 'level'>[]): {
    sourceCode: string;
    delta: number;
  }[] {
    const deltas: { sourceCode: string; delta: number }[] = [];
    if (abilities.some((ability) => ability.ruleCode === 'basic-element-properties' && ability.level > 0)) {
      deltas.push({ sourceCode: 'mastery', delta: 1 });
    }
    if (abilities.some((ability) => ability.ruleCode === 'magic-structure-interaction' && ability.level > 0)) {
      deltas.push({ sourceCode: 'mastery', delta: 2 });
    }

    return deltas;
  }

  apply(
    spec: DiceRollSpec,
    checkCode: string | null,
    deltas: readonly { sourceCode: string; delta: number }[],
  ): DiceRollSpec {
    if (checkCode !== CHECK_SPELL_CAST_CODE || deltas.length === 0) {
      return spec;
    }
    const sum = aggregateSourceDeltasService.netSourceDelta(
      deltas.map((entry) => ({ source_code: entry.sourceCode, delta: entry.delta })),
    );
    const next = new DimensionalNumber({ base: spec.efficiency, size: spec.efficiencySize ?? 0 }).modify(
      sum,
      CHARACTERISTIC_BASE_RANGE,
    );

    return { ...spec, efficiency: next.value.base, efficiencySize: next.value.size };
  }
}
