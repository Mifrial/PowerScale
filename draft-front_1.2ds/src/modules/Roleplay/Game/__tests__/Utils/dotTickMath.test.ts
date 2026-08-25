import { describe, expect, it } from 'vitest';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  advanceDotState,
  decayStrength,
  nextDotTurnsLeft,
  turnPeriod,
} from '@/modules/Roleplay/Game/Utils/dotTickMath';

const burning: Rule = {
  id: 'rule-burn',
  code: 'burning',
  type: 'state',
  name: 'Горение',
  description: '',
  spaceId: 1,
  spec: {
    value_type: 'dimensional',
    aggregation: 'sum',
    effects: [
      { type: 'damage_over_time', damage: { kind: 'value' }, periodicity: { kind: 'literal', value: 1, step: 'turn' } },
    ],
  },
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
};

const poisoning: Rule = {
  id: 'rule-poisoning',
  code: 'poisoning',
  type: 'state',
  name: 'Отравление',
  description: '',
  spaceId: 1,
  spec: { value_type: 'flag', aggregation: 'independent', effects: [] },
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
};

const scorpion: Rule = {
  id: 'rule-scorpion',
  code: 'poison-scorpion',
  type: 'poison',
  name: 'Яд скорпиона',
  description: '',
  spaceId: 1,
  spec: {
    damage_type_code: 'poison-1',
    default_strength: { base: 3, size: 1 },
    default_periodicity: { kind: 'literal', value: 2, step: 'turn' },
    default_decay: { kind: 'fixed', value: 1 },
  },
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
};

const hourly: Rule = {
  ...burning,
  id: 'rule-hourly',
  spec: {
    value_type: 'dimensional',
    aggregation: 'sum',
    effects: [
      { type: 'damage_over_time', damage: { kind: 'value' }, periodicity: { kind: 'literal', value: 1, step: 'hour' } },
    ],
  },
};

describe('dotTickMath', () => {
  it('period turn: 1 тикает сразу, 2 пропускает первый ход', () => {
    expect(turnPeriod({ kind: 'literal', value: 1, step: 'turn' })).toBe(1);
    expect(turnPeriod({ kind: 'literal', value: 2, step: 'hour' })).toBeNull();
    expect(nextDotTurnsLeft(undefined, 1)).toEqual({ fire: true, left: 1 });
    expect(nextDotTurnsLeft(undefined, 2)).toEqual({ fire: false, left: 1 });
    expect(nextDotTurnsLeft(1, 2)).toEqual({ fire: true, left: 2 });
  });

  it('затухание fixed снимает яд при силе 1', () => {
    expect(decayStrength({ base: 3, size: 1 }, { kind: 'fixed', value: 1 })).toEqual({ base: 2, size: 1 });
    expect(decayStrength({ base: 1, size: 0 }, { kind: 'fixed', value: 1 })).toBeNull();
  });

  it('горение period 1 тикает и остаётся без decay', () => {
    const state: CharacterStateValue = { stateRuleId: 'rule-burn', dimensionalValue: { base: 3, size: 1 } };
    const first = advanceDotState(state, [burning]);
    expect(first.kind).toBe('tick');
    if (first.kind !== 'tick') return;
    expect(first.damageTypeCode).toBe('fire');
    expect(first.next?.dotTurnsLeft).toBe(1);
    expect(first.next?.dimensionalValue).toEqual({ base: 3, size: 1 });
  });

  it('яд period 2: первый ход wait, второй tick и decay', () => {
    const state: CharacterStateValue = {
      stateRuleId: 'rule-poisoning',
      poison: { poisonRuleId: 'rule-scorpion' },
    };
    const first = advanceDotState(state, [poisoning, scorpion]);
    expect(first).toMatchObject({ kind: 'wait', next: { dotTurnsLeft: 1 } });
    const second = advanceDotState(first.kind === 'wait' ? first.next : state, [poisoning, scorpion]);
    expect(second.kind).toBe('tick');
    if (second.kind !== 'tick') return;
    expect(second.strength).toEqual({ base: 3, size: 1 });
    expect(second.next?.poison?.strength).toEqual({ base: 2, size: 1 });
    expect(second.next?.dotTurnsLeft).toBe(2);
  });

  it('step не turn — skip', () => {
    const state: CharacterStateValue = { stateRuleId: 'rule-hourly', dimensionalValue: { base: 2, size: 0 } };
    expect(advanceDotState(state, [hourly]).kind).toBe('skip');
  });
});
