import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { characteristicRollSpec, rollCharacteristic } from '@/modules/Roleplay/Game/Utils/characteristicRoll';

const RULES: Rule[] = [
  {
    id: 'rule-roll',
    code: 'roll',
    type: 'simple',
    name: 'Бросок',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: 5,
    mechanic_payload: {
      type: 'roll',
      data: { diceCount: 3, dieFaces: 6, efficiency: 3, adv: 0, sub_mechanics: ['six_one_rule'] },
    },
    createdAt: '2026-01-15T10:00:00Z',
  },
  {
    id: 'rule-6-and-1',
    code: 'rule-6-and-1',
    type: 'simple',
    name: 'Правило 6 и 1',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: 1,
    mechanic_payload: null,
    createdAt: '2026-01-15T10:00:00Z',
  },
  {
    id: 'rule-strength',
    code: 'strength',
    type: 'characteristic',
    name: 'Сила',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-01-15T10:00:00Z',
  },
];

const MECHANICS: Mechanic[] = [
  { id: 1, code: 'six_one_rule', name: 'Правило 6 и 1', description: '', version: '4.5.0' },
];

function fixedRng(...values: number[]): () => number {
  let index = 0;

  return () => values[index++ % values.length] ?? 0;
}

describe('characteristicRollSpec', () => {
  it('пул = база характеристики (минимум 1 куб)', () => {
    expect(characteristicRollSpec({ name: 'Сила', value: { base: 5, size: 0 } }, RULES).diceCount).toBe(5);
    expect(characteristicRollSpec({ name: 'Сила', value: { base: 0, size: 0 } }, RULES).diceCount).toBe(1);
  });

  it('грани/сложность берутся из правила «Бросок» ревизии (фолбэк 6/3 при отсутствии)', () => {
    const spec = characteristicRollSpec({ name: 'Сила', value: { base: 4, size: 0 } }, RULES);
    expect(spec.dieFaces).toBe(6);
    expect(spec.efficiency).toBe(3);
    expect(spec.adv).toBe(0);
    expect(spec.dieSize).toBe(0);
    expect(spec.label).toBe('Сила');

    const fallback = characteristicRollSpec({ name: 'Сила', value: { base: 4, size: 0 } }, []);
    expect(fallback.dieFaces).toBe(6);
    expect(fallback.efficiency).toBe(3);
  });

  it('размерность не уменьшает пул — пул = база, размерность уходит в dieSize броска', () => {
    const spec = characteristicRollSpec({ name: 'Сила', value: { base: 4, size: -1 } }, RULES);
    expect(spec.diceCount).toBe(4);
    expect(spec.dieSize).toBe(-1);
    expect(characteristicRollSpec({ name: 'Сила', value: { base: 3, size: -1 } }, RULES).diceCount).toBe(3);
    expect(characteristicRollSpec({ name: 'Сила', value: { base: 5, size: 2 } }, RULES).diceCount).toBe(5);
  });
});

describe('rollCharacteristic', () => {
  it('без механик — результат через rollService (successes по сложности)', () => {
    const result = rollCharacteristic(
      { name: 'Сила', value: { base: 2, size: 0 } },
      RULES,
      [],
      fixedRng(0.0, 0.5, 0.99),
    );
    expect(result.rolls).toHaveLength(2);
    expect(result.totalSuccesses).toBeGreaterThanOrEqual(0);
  });

  it('с механиками ревизии — через RollEngine (применяет «Правило 6 и 1»)', () => {
    const result = rollCharacteristic({ name: 'Сила', value: { base: 1, size: 0 } }, RULES, MECHANICS, fixedRng(0.0));
    expect(result.rolls).toEqual([1]);
    expect(result.successes).toEqual([2]);
    expect(result.appliedMechanics).toEqual(['Правило 6 и 1']);
  });
});
