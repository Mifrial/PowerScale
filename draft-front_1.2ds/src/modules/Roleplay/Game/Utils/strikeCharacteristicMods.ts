import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { ADVANTAGE_SOURCE_STATE } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

const FALLBACK_MASTERY: DimensionalNumberValue = { base: 3, size: -1 };
const STRIKE_STAT_CODES = ['dexterity', 'perception'] as const;
export const STRIKE_STAT_LABEL = 'Ловкость/Восприятие';

export function characteristicSizeByCode(
  overview: CharacterOverview | null,
  rules: Rule[],
  code: string,
): number | null {
  if (!overview) return null;
  for (const entry of overview.characteristics ?? []) {
    const rule = rules.find((item) => item.id === entry.ruleId);
    if (rule?.code === code) return entry.value.size;
  }

  return null;
}

/** Лучшее мастерство ББ среди тайлов экипированного оружия, иначе общее. */
export function bestMeleeMastery(overview: CharacterOverview | null): DimensionalNumberValue {
  const section = overview?.combat?.melee;
  if (!section) return FALLBACK_MASTERY;
  let best = section.stat.value;
  for (const weapon of section.weapons) {
    if (new DimensionalNumber(weapon.value).compare(new DimensionalNumber(best)) > 0) best = weapon.value;
  }

  return best;
}

/**
 * Ловкость/Восприятие для удара и защиты (не слой проверки):
 * −1 к мастерству за каждый размер, на который меньшая ниже среднего (`modifyWith`);
 * +1 к мастерству за каждый размер лучшей выше среднего;
 * преимущества от состояния, если обе выше среднего.
 */
export function strikeCharacteristicMods(
  overview: CharacterOverview | null,
  rules: Rule[],
): { masteryDelta: number; advantages: AdvantageModifier[] } {
  const sizes = STRIKE_STAT_CODES.map((code) => characteristicSizeByCode(overview, rules, code)).filter(
    (size): size is number => size != null,
  );
  if (sizes.length === 0) return { masteryDelta: 0, advantages: [] };
  const lesser = Math.min(...sizes);
  const greater = Math.max(...sizes);
  const masteryDelta = (lesser < 0 ? lesser : 0) + Math.max(0, greater);
  const advantages: AdvantageModifier[] = [];
  if (sizes.length === 2 && lesser > 0) {
    const dual = lesser >= greater ? 2 : greater - lesser === 1 ? 1 : 0;
    if (dual) {
      advantages.push({
        source_code: ADVANTAGE_SOURCE_STATE,
        source_label: STRIKE_STAT_LABEL,
        delta: dual,
      });
    }
  }

  return { masteryDelta, advantages };
}

export function applyStrikeMastery(mastery: DimensionalNumberValue, masteryDelta: number): DimensionalNumberValue {
  if (!masteryDelta) return mastery;

  return CharacteristicNumber.from(mastery).modifyWith(masteryDelta).value;
}
