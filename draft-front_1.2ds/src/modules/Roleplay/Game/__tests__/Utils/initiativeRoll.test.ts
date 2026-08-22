import { describe, expect, it } from 'vitest';
import { rollInitiative, orderInitiative } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import type { InitiativeRollEntry, InitiativeRollResult } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { GameInitiativeParticipant } from '@/modules/Roleplay/Game/Dto/GameInitiative';

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
  it('характеристика: пул = toNumber значения, результат = totalSuccesses', () => {
    const entry = base({
      participant: participant('character:1', 'А'),
      method: 'characteristic',
      characteristicValue: { base: 3, size: 0 },
      modifier: 0,
      adv: 0,
    });
    const [rolled] = rollInitiative([entry], rngFromDice([2, 4, 1]));

    expect(rolled.value).toBe(3);
    expect(rolled.result?.spec.diceCount).toBe(3);
  });

  it('характеристика: системный модификатор меняет пул (modifyWith)', () => {
    // (3).modifyWith(-3) → base 3, size -1 → toNumber = 1 куб.
    const entry = base({
      participant: participant('character:1', 'А'),
      method: 'characteristic',
      characteristicValue: { base: 3, size: 0 },
      modifier: -3,
      adv: 0,
    });
    const [rolled] = rollInitiative([entry], rngFromDice([2]));

    expect(rolled.result?.spec.diceCount).toBe(1);
    expect(rolled.value).toBe(1);
  });

  it('свободный бросок использует пул из правила «Бросок» и преимущества', () => {
    const entry = base({ participant: participant('npc:1', 'Дракон'), method: 'free', adv: 1 });
    const [rolled] = rollInitiative([entry], rngFromDice([2, 4, 1, 6]));

    expect(rolled.result?.spec.diceCount).toBe(3);
    expect(rolled.result?.spec.adv).toBe(1);
    expect(rolled.result?.droppedRolls).toEqual([6]);
    expect(rolled.value).toBe(3);
  });

  it('фиксированное значение не бросает кубы', () => {
    const entry = base({ participant: participant('character:2', 'Б'), method: 'fixed', fixedValue: 12 });
    const [rolled] = rollInitiative([entry], rngFromDice([2]));

    expect(rolled.value).toBe(12);
    expect(rolled.result).toBeNull();
  });
});

describe('orderInitiative', () => {
  function result(id: string, value: number): InitiativeRollResult {
    return { participant: participant(id, id), value, result: null };
  }

  it('сортирует по убыванию значения', () => {
    const ordered = orderInitiative([result('a', 1), result('b', 3), result('c', 2)], rngFromDice([]));
    expect(ordered.map((p) => p.id)).toEqual(['b', 'c', 'a']);
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
