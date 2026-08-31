import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';

import { SIMPLE_CHECK_ZERO_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Check/SIMPLE_CHECK_ZERO_DIFFICULTY';

import type { CharacteristicRollEntry } from '@/modules/Roleplay/Game/Dto/CharacteristicRollEntry';
export class CharacteristicRollService {
  /**
   * Спека проверки характеристики: пул = база движкового значения (минимум 1 куб),
   * грани/эффективность из правила «Бросок» ревизии, размерность значения (size) — в dieSize.
   */
  characteristicRollSpec(entry: CharacteristicRollEntry, rules: Rule[]): DiceRollSpec {
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
  rollCharacteristic(
    entry: CharacteristicRollEntry,
    rules: Rule[],
    mechanics: Mechanic[],
    rng: DiceRng = Math.random,
  ): DiceRollResult {
    const spec = this.characteristicRollSpec(entry, rules);
    const withMechanics = rules.length > 0 && mechanics.length > 0;
    const checkCode = entry.characteristicCode
      ? checkResolutionService.resolveCheckCodeForCharacteristic(entry.characteristicCode, rules)
      : checkResolutionService.resolveCheckCodeFromRuleId(entry.ruleId, rules);
    const attachedRuleCodes = checkResolutionService.resolveCheckAttachedRuleCodes(checkCode, rules);

    const rolled = withMechanics
      ? rollEngine.roll(spec, rng, rules, mechanics, attachedRuleCodes, [])
      : rollService.computeRollResult(spec, rng);

    return checkRollService.withCheckOutcome(rolled, checkCode, SIMPLE_CHECK_ZERO_DIFFICULTY);
  }
}
