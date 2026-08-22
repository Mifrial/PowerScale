import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { RaceCharacteristic } from '@/modules/Roleplay/Rule/Dto/Race/RaceCharacteristic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import {
  evaluateDerivedValue,
  parseDerivedFormula,
  type ParsedDerivedFormula,
} from '@/modules/Roleplay/Rule/Utils/derivedCharacteristic';

export interface RaceCharacteristicLabel {
  name: string;
  label: string;
}

const maxLadderValue = (entry: RaceCharacteristic): DimensionalNumberValue | null => {
  const ladder = entry.purchase ?? [];
  if (ladder.length === 0) return null;

  return ladder.reduce(
    (best, level) => (new DimensionalNumber(level.value).compare(new DimensionalNumber(best)) > 0 ? level.value : best),
    ladder[0].value,
  );
};

/**
 * Чипы характеристик карточки расы (RaceTab). Производные (Восприятие/Интеллект) не хранятся
 * в спеке — показываются одним значением, вычисленным из баз по формуле (своего значения нет).
 * Базовая характеристика, равная минимуму своей производной, скрывается (D85); докупаемая —
 * диапазоном «от base до max».
 */
export function buildRaceCharacteristicLabels(spec: RaceSpec, rules: Rule[]): RaceCharacteristicLabel[] {
  const byCode = new Map(rules.map((rule) => [rule.code, rule]));

  const derivedFormulas = new Map<string, ParsedDerivedFormula>();
  for (const rule of rules) {
    if (rule.type !== 'characteristic') continue;
    const formula = (rule.spec as { formula?: string | null } | undefined)?.formula;
    if (!formula) continue;
    const parsed = parseDerivedFormula(formula);
    if (parsed) derivedFormulas.set(rule.code, parsed);
  }

  const entryOf = (code: string): RaceCharacteristic | undefined =>
    spec.characteristics.find((c) => c.characteristic_code === code);
  const nameOf = (code: string): string => byCode.get(code)?.name ?? code;
  const valueOf = (code: string): DimensionalNumberValue | undefined => entryOf(code)?.base;

  // Минимум производной, базой которой является code (фильтр D85: база скрывается, если равна ему).
  const derivedMinOf = (code: string): DimensionalNumberValue | null => {
    for (const [, parsed] of derivedFormulas) {
      if (!parsed.codes.includes(code)) continue;
      const value = evaluateDerivedValue(parsed, valueOf);
      if (value !== null) return value;
    }

    return null;
  };

  const labels: RaceCharacteristicLabel[] = [];
  for (const characteristic of spec.characteristics) {
    const code = characteristic.characteristic_code;
    if (derivedFormulas.has(code)) continue;

    const derivedMin = derivedMinOf(code);
    if (derivedMin !== null && new DimensionalNumber(characteristic.base).equals(new DimensionalNumber(derivedMin)))
      continue;

    const baseLabel = new DimensionalNumber(characteristic.base).toString();
    if (characteristic.mode === 'fixed') {
      labels.push({ name: nameOf(code), label: baseLabel });
      continue;
    }
    const maxValue = maxLadderValue(characteristic);
    const sameAsBase =
      maxValue === null || new DimensionalNumber(maxValue).equals(new DimensionalNumber(characteristic.base));
    labels.push({
      name: nameOf(code),
      label: sameAsBase ? baseLabel : `от ${baseLabel} до ${new DimensionalNumber(maxValue).toString()}`,
    });
  }

  // Производные: единое значение из баз (у них нет своего значения).
  for (const [code, parsed] of derivedFormulas) {
    const value = evaluateDerivedValue(parsed, valueOf);
    if (value === null) continue;
    labels.push({ name: nameOf(code), label: new DimensionalNumber(value).toString() });
  }

  return labels;
}
