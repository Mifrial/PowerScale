import { describe, expect, it } from 'vitest';
import { rollInitiative, orderInitiative } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import type { InitiativeRollEntry, InitiativeRollResult } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { GameInitiativeParticipant } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/Service/Instance/aggregateSourceDeltasService';

function participant(id: string, name: string): GameInitiativeParticipant {
  return { id, name, kind: id.startsWith('npc') ? 'npc' : 'character', entityId: null };
}

function base(entry: Omit<InitiativeRollEntry, 'dieFaces' | 'efficiency' | 'freeDiceCount'>): InitiativeRollEntry {
  return { ...entry, dieFaces: 6, efficiency: 3, freeDiceCount: 3 };
}

function rngFromDice(values: number[], faces = 6): DiceRng {
  let i = 0;

  return () => (values[i++] - 1) / faces;
}

describe('rollInitiative', () => {
  it('характеристика: пул = база, размерность в dieSize', () => {
    const entry = base({
      participant: participant('character:1', 'А'),
      method: 'characteristic',
      characteristicCode: 'perception',
      characteristicValue: { base: 4, size: 0 },
      modifier: 0,
      adv: 0,
    });
    const [rolled] = rollInitiative([entry], rngFromDice([2, 4, 1, 5]));

    expect(rolled.result?.spec.diceCount).toBe(4);
    expect(rolled.result?.spec.dieSize).toBe(0);
  });

  it('характеристика: системный модификатор меняет шкалу, пул остаётся базой', () => {
    const entry = base({
      participant: participant('character:1', 'А'),
      method: 'characteristic',
      characteristicValue: { base: 3, size: 0 },
      modifier: -3,
      adv: 0,
    });
    const [rolled] = rollInitiative([entry], rngFromDice([2, 4, 1]));

    expect(rolled.result?.spec.diceCount).toBe(3);
    expect(rolled.result?.spec.dieSize).toBe(-1);
  });

  it('без значения характеристики не маскируется 1d6', () => {
    const entry = base({
      participant: participant('character:1', 'А'),
      method: 'characteristic',
      adv: 0,
    });
    expect(() => rollInitiative([entry], rngFromDice([2]))).toThrow(/Нет значения/);
  });

  it('свободный бросок использует пул из правила «Бросок» и преимущества', () => {
    const entry = base({ participant: participant('npc:1', 'Дракон'), method: 'free', adv: 1 });
    const [rolled] = rollInitiative([entry], rngFromDice([2, 4, 1, 6]));

    expect(rolled.result?.spec.diceCount).toBe(3);
    expect(aggregateSourceDeltasService.netSourceDelta(rolled.result?.spec.advantages ?? [])).toBe(1);
    expect(rolled.result?.droppedRolls).toEqual([6]);
    expect(rolled.value).toEqual({ base: 3, size: 0 });
  });

  it('фиксированное значение не бросает кубы', () => {
    const entry = base({ participant: participant('character:2', 'Б'), method: 'fixed', fixedValue: 12 });
    const [rolled] = rollInitiative([entry], rngFromDice([2]));

    expect(rolled.value).toEqual({ base: 12, size: 0 });
    expect(rolled.result).toBeNull();
  });

  it('механики броска — с проверки выбранной характеристики, не с check-initiative', () => {
    const rules: Rule[] = [
      {
        id: 'rule-check-simple',
        code: 'check-simple',
        type: 'check',
        name: 'Простая проверка',
        description: '',
        spaceId: 1,
        spec: {
          type: 'check',
          difficulty_input: { kind: 'ask' },
          allowed_modes: 'both',
          attached_rule_codes: ['advantages'],
        },
        createdAt: '2026-08-22T12:00:00Z',
      },
      {
        id: 'rule-check-initiative',
        code: 'check-initiative',
        type: 'check',
        name: 'Проверка на инициативу',
        description: '',
        spaceId: 1,
        spec: {
          type: 'check',
          parent_check_code: 'check-simple',
          characteristic_code: 'perception',
          allow_characteristic_override: true,
          difficulty_input: { kind: 'none' },
          allowed_modes: 'joint',
        },
        createdAt: '2026-08-22T12:00:00Z',
      },
      {
        id: 'rule-check-perception',
        code: 'check-perception',
        type: 'check',
        name: 'Проверка на Восприятие',
        description: '',
        spaceId: 1,
        spec: {
          type: 'check',
          parent_check_code: 'check-simple',
          characteristic_code: 'perception',
          difficulty_input: { kind: 'ask' },
          allowed_modes: 'both',
          attached_rule_codes: ['rule-6-and-1'],
        },
        createdAt: '2026-08-22T12:00:00Z',
      },
      {
        id: 'rule-check-attention',
        code: 'check-attention',
        type: 'check',
        name: 'Проверка на Внимательность',
        description: '',
        spaceId: 1,
        spec: {
          type: 'check',
          parent_check_code: 'check-simple',
          characteristic_code: 'attention',
          difficulty_input: { kind: 'ask' },
          allowed_modes: 'both',
          attached_rule_codes: [],
        },
        createdAt: '2026-08-22T12:00:00Z',
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
        createdAt: '2026-08-22T12:00:00Z',
      },
    ];
    const mechanics: Mechanic[] = [
      { id: 1, code: 'six_one_rule', name: 'Правило 6 и 1', description: '', version: '4.5.0' },
    ];
    const perception = base({
      participant: participant('character:1', 'Вася'),
      method: 'characteristic',
      characteristicCode: 'perception',
      characteristicValue: { base: 1, size: 0 },
    });
    const attention = base({
      participant: participant('character:2', 'Петя'),
      method: 'characteristic',
      characteristicCode: 'attention',
      characteristicValue: { base: 1, size: 0 },
    });
    const [vasya, petya] = rollInitiative([perception, attention], rngFromDice([1, 1]), rules, mechanics);

    expect(vasya.result?.appliedMechanics).toEqual(['Правило 6 и 1']);
    expect(petya.result?.appliedMechanics).toBeUndefined();
  });
});

describe('orderInitiative', () => {
  function result(id: string, value: number | { base: number; size: number }): InitiativeRollResult {
    const dimensional = typeof value === 'number' ? { base: value, size: 0 } : value;

    return { participant: participant(id, id), value: dimensional, result: null };
  }

  it('сортирует по убыванию значения', () => {
    const ordered = orderInitiative([result('a', 1), result('b', 3), result('c', 2)], rngFromDice([]));
    expect(ordered.map((p) => p.id)).toEqual(['b', 'c', 'a']);
  });

  it('3↓ меньше 2 — ходит позже', () => {
    const ordered = orderInitiative(
      [result('garrick', { base: 3, size: -1 }), result('beard', { base: 2, size: 0 })],
      rngFromDice([]),
    );
    expect(ordered.map((p) => p.id)).toEqual(['beard', 'garrick']);
  });

  it('4↓ равно 2 — ничья, случайный порядок', () => {
    const tieRng = (() => {
      let first = true;

      return () => {
        const v = first ? 0 : 0.99;
        first = false;

        return v;
      };
    })();
    const ordered = orderInitiative([result('x', { base: 4, size: -1 }), result('y', { base: 2, size: 0 })], tieRng);
    expect(ordered.map((p) => p.id)).toEqual(['y', 'x']);
  });

  it('равные значения — случайный порядок (заморожен в результате)', () => {
    const tieRng = (() => {
      // shuffle группы из двух: i=1 → j = floor(rng()*2); 0 → обмен.
      let first = true;

      return () => {
        const v = first ? 0 : 0.99;
        first = false;

        return v;
      };
    })();
    const ordered = orderInitiative([result('x', 2), result('y', 2)], tieRng);
    expect(ordered.map((p) => p.id)).toEqual(['y', 'x']);
  });

  it('стабилен при отсутствии rng-вызовов для одиночных значений', () => {
    const ordered = orderInitiative([result('a', 3), result('b', 1)], rngFromDice([]));
    expect(ordered.map((p) => p.id)).toEqual(['a', 'b']);
  });
});
