import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';

const FALLBACK_MASTERY: DimensionalNumberValue = { base: 3, size: -1 };

export function characteristicSizeByCode(
  overview: CharacterOverview | null,
  rules: { code: string }[],
  code: string,
): number | null {
  if (!overview) return null;
  for (const entry of overview.characteristics ?? []) {
    const rule = rules.find((item) => item.code === entry.ruleCode);
    if (rule?.code === code) return entry.value.size;
  }

  return null;
}

/** Лучшее мастерство ББ среди тайлов экипированного оружия, иначе общее. */
export function bestMeleeMastery(overview: CharacterOverview | null): DimensionalNumberValue {
  return bestCombatMastery(overview, false);
}

export function bestCombatMastery(overview: CharacterOverview | null, ranged: boolean): DimensionalNumberValue {
  const section = ranged ? overview?.combat?.ranged : overview?.combat?.melee;
  if (!section) return FALLBACK_MASTERY;
  let best = section.stat.value;
  for (const weapon of section.weapons) {
    if (new DimensionalNumber(weapon.value).compare(new DimensionalNumber(best)) > 0) best = weapon.value;
  }

  return best;
}
