import { describe, expect, it } from 'vitest';
import { RollEngine } from '@/modules/Roleplay/Game/Service/Roll/RollEngine';
import { MechanicEngine } from '@/modules/Roleplay/Rule/init';
import { MechanicHandlerRegistry } from '@/modules/Roleplay/Rule/init';
import { rollAdvantageHandler } from '@/modules/Roleplay/Rule/init';
import { rollSixOneHandler } from '@/modules/Roleplay/Rule/init';
import { rollCriticalStrikeHandler } from '@/modules/Roleplay/Rule/init';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

const MECHANICS: Mechanic[] = [
  { id: 1, code: 'six_one_rule', name: 'Правило 6 и 1', description: '', version: '4.5.0' },
  { id: 2, code: 'advantage_disadvantage', name: 'Помехи и преимущества', description: '', version: '2.1.0' },
  { id: 5, code: 'roll', name: 'Бросок', description: '', version: '1.0.0' },
  { id: 6, code: 'critical_strike', name: 'Критический удар', description: '', version: '1.0.0' },
];

function rule(overrides: Partial<Rule>): Rule {
  return {
    id: null,
    code: 'code-x',
    type: 'simple',
    name: 'Правило',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-01-15T10:00:00Z',
    ...overrides,
  };
}

function rollRule(
  data: Partial<{
    diceCount: number;
    dieFaces: number;
    efficiency: number;
    adv: number;
    dieSize: number;
    sub_mechanics: string[];
  }>,
): Rule {
  return rule({ id: null, code: 'roll', mechanicId: 5, mechanic_payload: { type: 'roll', data } });
}

const ROLL_SUB_MECHANICS = ['six_one_rule', 'advantage_disadvantage'];

function spec(partial: Partial<DiceRollSpec> & { adv?: number } = {}): DiceRollSpec {
  const { adv, advantages, ...rest } = partial;

  return {
    diceCount: 4,
    dieFaces: 6,
    efficiency: 3,
    dieSize: 0,
    ...rest,
    advantages: advantages ?? aggregateSourceDeltasService.advantageEntries(adv ?? 0),
  };
}

function rngFromDice(values: number[], faces = 6): DiceRng {
  let i = 0;

  return () => (values[i++] - 1) / faces;
}

function createEngine(): RollEngine {
  const registry = new MechanicHandlerRegistry();
  registry.register(rollAdvantageHandler);
  registry.register(rollSixOneHandler);
  registry.register(rollCriticalStrikeHandler);

  return new RollEngine(new MechanicEngine(registry));
}

describe('RollEngine: без механик', () => {
  it('бросок по чистым параметрам спеки (базовый подсчёт, без «6 и 1» и преимуществ)', () => {
    const result = createEngine().roll(spec({ diceCount: 4 }), rngFromDice([2, 4, 1, 5]), [], []);
    expect(result.rolls).toEqual([2, 4, 1, 5]);
    expect(result.adjustedRolls).toEqual([2, 4, 1, 5]);
    expect(result.successes).toEqual([1, 0, 1, 0]);
    expect(result.totalSuccesses).toBe(2);
    expect(result.appliedMechanics).toBeUndefined();
  });
});

describe('RollEngine: механики ревизии', () => {
  const rules = [
    rollRule({ efficiency: 3, sub_mechanics: ROLL_SUB_MECHANICS }),
    rule({ id: 1, code: 'rule-6-and-1', mechanicId: 1 }),
    rule({ id: 2, code: 'advantages', mechanicId: 2 }),
  ];

  it('преимущества и «6 и 1» меняют бросок и попадают в appliedMechanics', () => {
    const result = createEngine().roll(spec({ diceCount: 3, adv: 1 }), rngFromDice([1, 6, 5, 6]), rules, MECHANICS);
    expect(result.droppedRolls).toEqual([6]);
    expect(result.adjustedRolls).toEqual([6, 5, 1]);
    expect(result.successes).toEqual([-1, 0, 2]);
    expect(result.totalSuccesses).toBe(1);
    expect(result.appliedMechanics).toEqual(['Помехи и преимущества', 'Правило 6 и 1']);
  });

  it('шестёрка — провал только при сложности ниже грани (движковое «6 и 1»)', () => {
    const low = createEngine().roll(spec({ diceCount: 1, efficiency: 3 }), rngFromDice([6]), rules, MECHANICS);
    expect(low.successes).toEqual([-1]);
    const high = createEngine().roll(spec({ diceCount: 1, efficiency: 6 }), rngFromDice([6]), rules, MECHANICS);
    expect(high.successes).toEqual([1]);
  });

  it('без правила «6 и 1» единица — обычный успех, грань — просто промах', () => {
    const withoutSixOne = [rules[0], rules[2]];
    const result = createEngine().roll(spec({ diceCount: 2, adv: 0 }), rngFromDice([1, 6]), withoutSixOne, MECHANICS);
    expect(result.successes).toEqual([1, 0]);
    expect(result.totalSuccesses).toBe(1);
    expect(result.appliedMechanics).toBeUndefined();
  });

  it('преимущества не помечаются, если adv = 0', () => {
    const result = createEngine().roll(spec({ diceCount: 1, adv: 0 }), rngFromDice([3]), rules, MECHANICS);
    expect(result.appliedMechanics).toBeUndefined();
  });

  it('subMechanicCodes подменяет набор «всегда в силе» (проверка без 6 и 1)', () => {
    const withSix = createEngine().roll(spec({ diceCount: 1 }), rngFromDice([6]), rules, MECHANICS);
    expect(withSix.successes).toEqual([-1]);
    const without = createEngine().roll(
      spec({ diceCount: 1 }),
      rngFromDice([6]),
      rules,
      MECHANICS,
      [],
      ['advantage_disadvantage'],
    );
    expect(without.successes).toEqual([0]);
  });
});

describe('RollEngine: пер-ролл механики (Критический удар)', () => {
  const rules = [
    rollRule({ efficiency: 3, sub_mechanics: ROLL_SUB_MECHANICS }),
    rule({ id: 1, code: 'rule-6-and-1', mechanicId: 1 }),
    rule({
      id: null,
      code: 'critical-strike',
      type: 'ability',
      mechanicId: 6,
      mechanic_payload: { type: 'roll_score_adjust', data: { oneDelta: 1, faceDelta: -1 } },
    }),
  ];

  it('крит удар поверх «6 и 1»: 1 → 3 успеха, грань → −2', () => {
    const engine = createEngine();
    const one = engine.roll(spec({ diceCount: 1 }), rngFromDice([1]), rules, MECHANICS, ['critical-strike']);
    expect(one.successes).toEqual([3]);
    expect(one.appliedMechanics).toEqual(['Правило 6 и 1', 'Критический удар']);

    const face = engine.roll(spec({ diceCount: 1 }), rngFromDice([6]), rules, MECHANICS, ['critical-strike']);
    expect(face.successes).toEqual([-2]);
  });

  it('без активации крит удар не применяется', () => {
    const result = createEngine().roll(spec({ diceCount: 1 }), rngFromDice([6]), rules, MECHANICS);
    expect(result.successes).toEqual([-1]);
    expect(result.appliedMechanics).toEqual(['Правило 6 и 1']);
  });
});

describe('RollEngine: дефолты из правила «Бросок»', () => {
  it('заполняет нейтральные параметры (сложность/преимущества/размерность), кубы/грани не трогает', () => {
    const result = createEngine().roll(
      spec({ diceCount: 4, dieFaces: 8 }),
      rngFromDice([3, 3, 3, 3], 8),
      [rollRule({ efficiency: 2, adv: 1, dieSize: 3 })],
      MECHANICS,
    );
    expect(result.spec.efficiency).toBe(2);
    expect(aggregateSourceDeltasService.netSourceDelta(result.spec.advantages)).toBe(1);
    expect(result.spec.dieSize).toBe(3);
    expect(result.spec.diceCount).toBe(4);
    expect(result.spec.dieFaces).toBe(8);
  });

  it('явно заданные пользователем параметры не перезаписываются', () => {
    const result = createEngine().roll(
      spec({ diceCount: 4, efficiency: 5, adv: -2 }),
      rngFromDice([3, 3, 3, 3]),
      [rollRule({ efficiency: 2, adv: 1 })],
      MECHANICS,
    );
    expect(result.spec.efficiency).toBe(5);
    expect(aggregateSourceDeltasService.netSourceDelta(result.spec.advantages)).toBe(-2);
  });

  it('помехи/преимущества разных источников суммируются, одного — max+/min−', () => {
    const rules = [
      rollRule({ sub_mechanics: ['advantage_disadvantage'] }),
      rule({ id: 2, code: 'advantages', mechanicId: 2 }),
    ];
    const stacked = createEngine().roll(
      spec({
        diceCount: 2,
        advantages: [
          { source_code: 'tool', source_label: null, delta: -1 },
          { source_code: 'tool', source_label: null, delta: -2 },
          { source_code: 'manual', source_label: null, delta: 1 },
        ],
      }),
      rngFromDice([1, 2, 6, 3]),
      rules,
      MECHANICS,
    );
    expect(stacked.rolls).toHaveLength(3);
    expect(stacked.droppedRolls).toHaveLength(1);
  });
});
