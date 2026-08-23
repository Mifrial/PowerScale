import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import {
  resolveCheckCodeForCharacteristic,
  resolveCheckCodeFromRuleId,
  resolveCheckAttachedRuleCodes,
} from '@/modules/Roleplay/Rule/Utils/checkResolution';
import { withCheckOutcome } from '@/modules/Roleplay/Game/Utils/checkRoll';
import { SIMPLE_CHECK_ZERO_DIFFICULTY } from '@/modules/Roleplay/Game/Utils/simpleCheckRoll';

/** Проверка характеристики: пул = база, размерность уходит в dieSize броска. */
export interface CharacteristicRollEntry {
  /** Имя характеристики (label броска и текст сообщения в чат). */
  name: string;
  value: DimensionalNumberValue;
  /** Код характеристики → проверка `check-{code}`. */
  characteristicCode?: string | null;
  /** ruleId характеристики или проверки, если кода нет. */
  ruleId?: string | null;
  actorKey?: CombatEntityKey;
}

/**
 * Спека проверки характеристики: пул = база движкового значения (минимум 1 куб),
 * грани/эффективность из правила «Бросок» ревизии, размерность значения (size) — в dieSize.
 */
export function characteristicRollSpec(entry: CharacteristicRollEntry, rules: Rule[]): DiceRollSpec {
  const defaults = rollPoolDefaults(rules);
  const pool = Math.max(1, entry.value.base);

  return {
    diceCount: pool,
    dieFaces: defaults.dieFaces,
    efficiency: defaults.efficiency,
    advantages: [],
    dieSize: entry.value.size,
    poolSize: entry.value.size,
    efficiencySize: 0,
    label: entry.name,
    actorKey: entry.actorKey,
  };
}

/**
 * Мгновенный бросок характеристики карточки. Механики скоринга — с проверки
 * (`check-strength` / простая проверка), не с правила «Бросок».
 */
export function rollCharacteristic(
  entry: CharacteristicRollEntry,
  rules: Rule[],
  mechanics: Mechanic[],
  rng: DiceRng = Math.random,
): DiceRollResult {
  const spec = characteristicRollSpec(entry, rules);
  const withMechanics = rules.length > 0 && mechanics.length > 0;
  const checkCode = entry.characteristicCode
    ? resolveCheckCodeForCharacteristic(entry.characteristicCode, rules)
    : resolveCheckCodeFromRuleId(entry.ruleId, rules);
  const attachedRuleCodes = resolveCheckAttachedRuleCodes(checkCode, rules);

  const rolled = withMechanics
    ? rollEngine.roll(spec, rng, rules, mechanics, attachedRuleCodes, [])
    : rollService.computeRollResult(spec, rng);

  return withCheckOutcome(rolled, checkCode, SIMPLE_CHECK_ZERO_DIFFICULTY);
}
