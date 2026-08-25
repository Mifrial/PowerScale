import type { RangedHitDifficultyBreakdown } from '@/modules/Roleplay/Game/Dto/DiceRollCheckOutcome';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { RangedHitDifficultyParts } from '@/modules/Roleplay/Character/Utils/weaponAttackRange';

function reactionLabel(reaction: HitDefenseReaction): string {
  if (reaction === 'dodge') return 'уклон';
  if (reaction === 'block') return 'блок';

  return 'игнор';
}

function rangeLabel(detail: RangedHitDifficultyBreakdown): string {
  const distance = `${detail.distance_ipari} ипари`;
  if (detail.range_size <= 0) return `без полос (${distance})`;

  return `+${detail.range_size} размер (${distance})`;
}

/** Строки [i]: как сложилась сложность броска/выстрела. */
export function rangedHitDifficultyDetailRows(
  detail: RangedHitDifficultyBreakdown,
): { label: string; value: string }[] {
  const floor = Math.max(1, detail.defense_result);
  const base = floor + detail.cover;

  return [
    { label: 'Реакция', value: reactionLabel(detail.reaction) },
    { label: 'Результат защиты', value: String(detail.defense_result) },
    { label: 'Укрытие', value: String(detail.cover) },
    { label: 'База', value: `max(1, ${detail.defense_result}) + ${detail.cover} = ${base}` },
    { label: 'Дальность', value: rangeLabel(detail) },
  ];
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
