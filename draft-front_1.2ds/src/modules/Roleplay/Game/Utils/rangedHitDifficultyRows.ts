import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { RangedHitDifficultyBreakdown } from '@/modules/Roleplay/Game/Dto/DiceRollCheckOutcome';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { RangedHitDifficultyParts } from '@/modules/Roleplay/Character/Dto/RangedHitDifficultyParts';

function reactionLabel(reaction: HitDefenseReaction): string {
  if (reaction === 'dodge') return 'уклон';
  if (reaction === 'block') return 'блок';

  return 'игнор';
}

function dimensionalLabel(value: DimensionalNumberValue | null | undefined): string | null {
  if (!value) return null;
  if (value.size === 0) return String(value.base);
  const power = Math.abs(value.size);

  return `${value.base}${value.size > 0 ? '↑' : '↓'}${power > 1 ? power : ''}`;
}

function dimensionalOr(value: DimensionalNumberValue | null | undefined, fallback: number): string {
  return dimensionalLabel(value) ?? String(fallback);
}

function rangeLabel(detail: RangedHitDifficultyBreakdown): string {
  const distance = dimensionalOr(detail.distance_value, detail.distance_ipari);
  const extra = Math.max(0, detail.range_size - 1);
  const falloff = dimensionalLabel(detail.falloff_value);
  const range = falloff === null ? '' : ` · дальнобойность ${falloff}`;
  const band = extra === 0 ? 'без штрафа' : `+${extra} ${extra === 1 ? 'размер' : 'размера'}`;

  return `${distance} ипари${range} → ${band}`;
}

/** Строки [i]: как сложилась сложность броска/выстрела. */
export function rangedHitDifficultyRows(detail: RangedHitDifficultyBreakdown): { label: string; value: string }[] {
  const defense = dimensionalOr(detail.defense_value, detail.defense_result);
  const cover = dimensionalOr(detail.cover_value, detail.cover);
  const minimum = dimensionalLabel(detail.minimum_value);
  const base = dimensionalLabel(detail.base_value);

  const rows = [
    { label: 'Реакция', value: reactionLabel(detail.reaction) },
    { label: 'Защита', value: defense },
    { label: 'Укрытие', value: cover },
  ];
  if (minimum !== null && base !== null) {
    rows.push({ label: 'Минимум', value: minimum });
    rows.push({ label: 'База', value: `${defense} + ${minimum} + ${cover} = ${base}` });
  } else if (base !== null) {
    rows.push({ label: 'База', value: base });
  }
  rows.push({ label: 'Дальность', value: rangeLabel(detail) });

  return rows;
}

/** Прежнее имя: разбор сложности попадания. */
export const rangedHitDifficultyDetailRows = rangedHitDifficultyRows;

export function withRangedHitBreakdown(
  result: DiceRollResult,
  parts: RangedHitDifficultyParts,
  reaction: HitDefenseReaction,
): DiceRollResult {
  if (!result.check) return result;

  return {
    ...result,
    check: {
      ...result.check,
      ranged_hit: {
        cover: parts.cover,
        defense_result: parts.checkResult,
        reaction,
        range_size: parts.rangeSize,
        distance_ipari: parts.distanceIpari,
      },
    },
  };
}
