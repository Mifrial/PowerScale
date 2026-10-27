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

function coverLabel(detail: RangedHitDifficultyBreakdown): string {
  return dimensionalLabel(detail.cover_value) ?? String(detail.cover);
}

function distanceLabel(detail: RangedHitDifficultyBreakdown): string {
  return dimensionalLabel(detail.distance_value) ?? String(detail.distance_ipari);
}

function rangeLabel(detail: RangedHitDifficultyBreakdown): string {
  const distance = `${distanceLabel(detail)} ипари`;
  const extra = Math.max(0, detail.range_size - 1);
  if (extra === 0) return `без полос (${distance})`;

  return `+${extra} размер (${distance})`;
}

/** Строки [i]: как сложилась сложность броска/выстрела. */
export function rangedHitDifficultyDetailRows(
  detail: RangedHitDifficultyBreakdown,
): { label: string; value: string }[] {
  const base = detail.defense_result + 0.5 + detail.cover;
  const cover = coverLabel(detail);
  const rows = [
    { label: 'Реакция', value: reactionLabel(detail.reaction) },
    { label: 'Результат защиты', value: String(detail.defense_result) },
    { label: 'Укрытие', value: cover },
    { label: 'База', value: `${detail.defense_result} + ½ + ${cover} = ${base}` },
    { label: 'Дальность', value: rangeLabel(detail) },
  ];
  const difficulty = dimensionalLabel(detail.difficulty);
  if (difficulty !== null) rows.push({ label: 'Сложность', value: difficulty });

  return rows;
}

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
