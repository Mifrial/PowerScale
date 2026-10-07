import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DefenseOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { HitBlockProfile } from '@/modules/Roleplay/Game/Dto/HitBlockProfile';
import { DAMAGE_TYPE_FORMS } from '@/modules/Roleplay/Rule/Constant/DAMAGE_TYPE_FORMS';

const BLOCK_LAYER_DURABILITY = 99;

/** РУ и слои защиты при реакции «Блок». */
export class BlockHitService {
  attackSr(reaction: HitDefenseReaction | null | undefined, rolledSr: number): number {
    if (reaction !== 'block') return Math.max(0, rolledSr);
    if (rolledSr > 0) return rolledSr;

    return 1;
  }

  withBlockLayers(
    defense: DefenseOverview | null,
    profile: HitBlockProfile | null | undefined,
  ): DefenseOverview | null {
    if (!profile || !defense) return defense;
    const defenseValue = new DimensionalNumber(profile.defense);
    const lines = [
      {
        kind: 'defense' as const,
        value: defenseValue.toNumber(),
        valueLabel: defenseValue.toString(),
        durability: BLOCK_LAYER_DURABILITY,
        sourceCode: profile.itemRuleCode,
        sourceLabel: profile.itemName,
        damageTypeLabel: null,
        damageTypeDative: null,
        damageTypeCode: null,
      },
      ...profile.resistances.map((slot) => {
        const value = new DimensionalNumber(slot.value);

        return {
          kind: 'resistance' as const,
          value: value.toNumber(),
          valueLabel: value.toString(),
          durability: slot.durability === null ? null : Math.max(slot.durability, BLOCK_LAYER_DURABILITY),
          sourceCode: slot.source_code ?? profile.itemRuleCode,
          sourceLabel: profile.itemName,
          damageTypeLabel: slot.damage_type_code ? (DAMAGE_TYPE_FORMS[slot.damage_type_code]?.dative ?? null) : null,
          damageTypeDative: slot.damage_type_code ? (DAMAGE_TYPE_FORMS[slot.damage_type_code]?.dative ?? null) : null,
          damageTypeCode: slot.damage_type_code,
        };
      }),
    ];

    return {
      ...defense,
      armor: [
        ...defense.armor,
        {
          itemRuleCode: profile.itemRuleCode,
          itemName: profile.itemName,
          href: '',
          lines,
          tiers: [],
        },
      ],
    };
  }
}
