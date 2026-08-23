import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RollMechanicContext } from '@/modules/Roleplay/Rule/Dto/RollMechanicContext';
import type { MechanicEngine } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicEngine';
import { ROLL_EVENTS } from '@/modules/Roleplay/Rule/init';
import {
  ROLL_RULE_CODE,
  ROLL_DEFAULT_EFFICIENCY,
  ROLL_DEFAULT_DIE_SIZE,
} from '@/modules/Roleplay/Game/Constant/Roll/ROLL_RULE_CODE';
import { ADVANTAGE_SOURCE_ROLL } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { advantageEntries } from '@/modules/Roleplay/Rule/Utils/aggregateSourceDeltas';

/**
 * Поток броска как события механик (ТР §8 «Броски»): pool → бросок → drop → базовый
 * подсчёт → score → итог. Механики ревизии (правила 6-и-1/преимущества, «если есть»)
 * и пер-ролл механики (навыки: Критический удар) подписаны на события; базовый подсчёт
 * «≤ сложность → 1 успех» и применение `dieSize` — примитивы движка. Без механик —
 * бросок по чистым параметрам спеки.
 */
export class RollEngine {
  constructor(private readonly engine: MechanicEngine) {}

  /** Дефолты спеки из правила «Бросок» ревизии (нейтральные параметры — как в прежнем resolveFromRevision). */
  resolveDefaults(rules: Rule[], spec: DiceRollSpec): DiceRollSpec {
    const rule = rules.find((candidate) => candidate.code === ROLL_RULE_CODE);
    const payload = rule?.mechanic_payload;
    if (!rule || !payload || payload.type !== 'roll') return spec;
    const data = payload.data;

    return {
      ...spec,
      efficiency:
        spec.efficiency === ROLL_DEFAULT_EFFICIENCY && data.efficiency !== undefined
          ? data.efficiency
          : spec.efficiency,
      dieSize: spec.dieSize === ROLL_DEFAULT_DIE_SIZE && data.dieSize !== undefined ? data.dieSize : spec.dieSize,
      advantages: spec.advantages.length > 0 ? spec.advantages : advantageEntries(data.adv ?? 0, ADVANTAGE_SOURCE_ROLL),
    };
  }

  roll(
    spec: DiceRollSpec,
    rng: DiceRng,
    rules: Rule[],
    mechanics: Mechanic[],
    activeRuleCodes: string[] = [],
    subMechanicCodes?: string[],
  ): DiceRollResult {
    const resolvedSpec = this.resolveDefaults(rules, spec);
    const context: RollMechanicContext = {
      diceCount: resolvedSpec.diceCount,
      dieFaces: resolvedSpec.dieFaces,
      efficiency: resolvedSpec.efficiency,
      advantages: resolvedSpec.advantages,
      poolSize: resolvedSpec.diceCount,
      rolls: [],
      adjustedRolls: [],
      droppedRolls: [],
      successes: [],
      totalSuccesses: 0,
      applied: [],
    };

    // «Всегда в силе» у голого броска — sub_mechanics правила «Бросок» (помехи/преимущества).
    // Проверка передаёт коды привязанных правил в extraRuleCodes и пустой includeCodes.
    const rollRule = rules.find((candidate) => candidate.code === ROLL_RULE_CODE);
    const includeCodes =
      subMechanicCodes !== undefined
        ? subMechanicCodes
        : rollRule?.mechanic_payload?.type === 'roll'
          ? rollRule.mechanic_payload.data.sub_mechanics
          : undefined;
    const active = this.engine.resolveActive(rules, mechanics, {
      includeCodes,
      extraRuleCodes: activeRuleCodes,
    });
    this.engine.runEvent(ROLL_EVENTS.pool, context, active);

    const rollDie = () => Math.floor(rng() * context.dieFaces) + 1;
    context.rolls = Array.from({ length: Math.max(1, context.poolSize) }, rollDie);

    this.engine.runEvent(ROLL_EVENTS.drop, context, active);
    context.adjustedRolls = context.adjustedRolls.length > 0 ? context.adjustedRolls : [...context.rolls];
    context.successes = context.adjustedRolls.map((value) => (value <= context.efficiency ? 1 : 0));

    this.engine.runEvent(ROLL_EVENTS.score, context, active);
    context.totalSuccesses = context.successes.reduce((sum, value) => sum + value, 0);

    const appliedMechanics = this.appliedNames(context.applied, mechanics);

    return {
      spec: resolvedSpec,
      rolls: context.rolls,
      successes: context.successes,
      adjustedRolls: context.adjustedRolls,
      droppedRolls: context.droppedRolls,
      totalSuccesses: context.totalSuccesses,
      appliedMechanics: appliedMechanics.length > 0 ? appliedMechanics : undefined,
    };
  }

  private appliedNames(codes: string[], mechanics: Mechanic[]): string[] {
    const byCode = new Map(mechanics.map((mechanic) => [mechanic.code, mechanic.name]));

    return codes.map((code) => byCode.get(code) ?? code);
  }
}
