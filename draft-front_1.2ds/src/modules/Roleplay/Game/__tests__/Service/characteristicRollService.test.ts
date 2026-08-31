import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { characteristicRollService } from '@/modules/Roleplay/Game/Service/Instance/characteristicRollService';

const RULES: Rule[] = [
  {
    id: null,
    code: 'roll',
    type: 'simple',
    name: 'Бросок',
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanicId: 5,
    mechanic_payload: {
      type: 'roll',
      data: { diceCount: 3, dieFaces: 6, efficiency: 3, adv: 0, sub_mechanics: ['advantage_disadvantage'] },
    },
    createdAt: '2026-01-15T10:00:00Z',
  },
  {
    id: null,
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
    id: null,
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
  {
    id: null,
    code: 'check-simple',
    type: 'check',
    name: 'Простая проверка',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'both',
      attached_rule_codes: ['rule-6-and-1', 'advantages'],
    },
    createdAt: '2026-08-22T12:00:00Z',
  },
  {
    id: null,
    code: 'check-strength',
    type: 'check',
    name: 'Проверка на Силу',
    description: '',
    spaceId: 1,
    spec: {
      type: 'check',
      parent_check_code: 'check-simple',
      characteristic_code: 'strength',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'both',
    },
    createdAt: '2026-08-22T12:00:00Z',
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
    expect(
      characteristicRollService.characteristicRollSpec({ name: 'Сила', value: { base: 5, size: 0 } }, RULES).diceCount,
    ).toBe(5);
    expect(
      characteristicRollService.characteristicRollSpec({ name: 'Сила', value: { base: 0, size: 0 } }, RULES).diceCount,
    ).toBe(1);
  });

  it('грани/сложность берутся из правила «Бросок» ревизии (фолбэк 6/3 при отсутствии)', () => {
    const spec = characteristicRollService.characteristicRollSpec({ name: 'Сила', value: { base: 4, size: 0 } }, RULES);
    expect(spec.dieFaces).toBe(6);
    expect(spec.efficiency).toBe(3);
    expect(spec.advantages).toEqual([]);
    expect(spec.dieSize).toBe(0);
    expect(spec.label).toBe('Сила');
    expect(
      characteristicRollService.characteristicRollSpec(
        { name: 'Сила', value: { base: 4, size: 0 }, actorKey: 'npc:2' },
        RULES,
      ).actorKey,
    ).toBe('npc:2');

    const fallback = characteristicRollService.characteristicRollSpec(
      { name: 'Сила', value: { base: 4, size: 0 } },
      [],
    );
    expect(fallback.dieFaces).toBe(6);
    expect(fallback.efficiency).toBe(3);
  });

  it('размерность не уменьшает пул — пул = база, размерность уходит в dieSize броска', () => {
    const spec = characteristicRollService.characteristicRollSpec(
      { name: 'Сила', value: { base: 4, size: -1 } },
      RULES,
    );
    expect(spec.diceCount).toBe(4);
    expect(spec.dieSize).toBe(-1);
    expect(
      characteristicRollService.characteristicRollSpec({ name: 'Сила', value: { base: 3, size: -1 } }, RULES).diceCount,
    ).toBe(3);
    expect(
      characteristicRollService.characteristicRollSpec({ name: 'Сила', value: { base: 5, size: 2 } }, RULES).diceCount,
    ).toBe(5);
  });
});

describe('rollCharacteristic', () => {
  it('без механик — результат через rollService (successes по сложности)', () => {
    const result = characteristicRollService.rollCharacteristic(
      { name: 'Сила', value: { base: 2, size: 0 } },
      RULES,
      [],
      fixedRng(0.0, 0.5, 0.99),
    );
    expect(result.rolls).toHaveLength(2);
    expect(result.totalSuccesses).toBeGreaterThanOrEqual(0);
  });

  it('с механиками ревизии — через RollEngine (применяет «Правило 6 и 1»)', () => {
    const result = characteristicRollService.rollCharacteristic(
      { name: 'Сила', value: { base: 1, size: 0 }, characteristicCode: 'strength' },
      RULES,
      MECHANICS,
      fixedRng(0.0),
    );
    expect(result.rolls).toEqual([1]);
    expect(result.successes).toEqual([2]);
    expect(result.appliedMechanics).toEqual(['Правило 6 и 1']);
    expect(result.check?.check_code).toBe('check-strength');
    expect(result.check?.difficulty).toEqual({ base: 0, size: 0 });
    expect(result.check?.passed).toBe(true);
  });
});
