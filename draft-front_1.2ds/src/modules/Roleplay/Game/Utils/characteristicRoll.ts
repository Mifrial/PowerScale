import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';

/** Проверка характеристики боевой карточки: пул = база, размерность уходит в dieSize броска. */
export interface CharacteristicRollEntry {
  /** Имя характеристики (label броска и текст сообщения в чат). */
  name: string;
  value: DimensionalNumberValue;
}

/**
 * Спека проверки характеристики: пул = база движкового значения (минимум 1 куб),
 * грани/сложность из правила «Бросок» ревизии, размерность значения (size) — в dieSize
 * броска (результат отдаётся «в размерности»: 3↓ → 3 куба, итог ↓1).
 */
export function characteristicRollSpec(entry: CharacteristicRollEntry, rules: Rule[]): DiceRollSpec {
  const defaults = rollPoolDefaults(rules);
  const pool = Math.max(1, entry.value.base);

  return {
    diceCount: pool,
    dieFaces: defaults.dieFaces,
    efficiency: defaults.efficiency,
    adv: 0,
    dieSize: entry.value.size,
    label: entry.name,
  };
}

/**
 * Мгновенный бросок характеристики карточки. С механиками ревизии (правила + механики)
 * бросок идёт через RollEngine — подчиняется правилам подсчёта (6-и-1 и пр.).
 */
export function rollCharacteristic(
  entry: CharacteristicRollEntry,
  rules: Rule[],
  mechanics: Mechanic[],
  rng: DiceRng = Math.random,
): DiceRollResult {
  const spec = characteristicRollSpec(entry, rules);
  const withMechanics = rules.length > 0 && mechanics.length > 0;

  return withMechanics ? rollEngine.roll(spec, rng, rules, mechanics) : rollService.computeRollResult(spec, rng);
}
