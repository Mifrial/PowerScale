import { describe, it, expect } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import { CharacterOverviewService } from '@/modules/Roleplay/Character/Service/Overview/CharacterOverviewService';

function stateRule(
  id: number | null,
  code: string,
  name: string,
  spec: { value_type: 'flag' | 'number' | 'dimensional'; aggregation: 'sum' | 'max' | 'independent' },
): Rule {
  return {
    id,
    code,
    type: 'state',
    name,
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
    spec: { ...spec, icon_code: null, effects: [] },
  };
}

function poisonRule(id: number | null, code: string, name: string, spec: Partial<PoisonSpec>): Rule {
  return {
    id,
    code,
    type: 'poison',
    name,
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
    spec: {
      damage_type_code: 'poison-1',
      default_strength: { base: 3, size: 0 },
      default_periodicity: { kind: 'literal', value: 2, step: 'turn' },
      default_decay: { kind: 'fixed', value: 1 },
      ...spec,
    },
  };
}

function damageTypeRule(id: number | null, code: string, name: string): Rule {
  return {
    id,
    code,
    type: 'damage_type',
    name,
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
  };
}

function version(states: CharacterStateValue[]): CharacterVersion {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: null,
    characteristics: [],
    resources: [],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 0 },
    money: 0,
    ageYears: null,
    inventory: [],
    states,
    senses: [],
  };
}

describe('CharacterOverviewService: состояния и агрегация повторов', () => {
  it('sum: несколько горений складываются в одну строку', () => {
    const rules: Rule[] = [stateRule(null, 'burning', 'Горение', { value_type: 'dimensional', aggregation: 'sum' })];
    const states = serviceStates(rules, [
      { stateRuleCode: 'burning', dimensionalValue: { base: 2, size: 0 } },
      { stateRuleCode: 'burning', dimensionalValue: { base: 3, size: 0 } },
    ]);

    expect(states).toHaveLength(1);
    expect(states[0]).toMatchObject({
      ruleCode: 'burning',
      name: 'Горение',
      valueLabel: '5',
      count: 2,
      aggregation: 'sum',
    });
  });

  it('max: из повторов берётся наибольшее значение', () => {
    const rules: Rule[] = [stateRule(null, 'weakness', 'Слабость', { value_type: 'number', aggregation: 'max' })];
    const states = serviceStates(rules, [
      { stateRuleCode: 'weakness', value: 2 },
      { stateRuleCode: 'weakness', value: 5 },
    ]);

    expect(states).toHaveLength(1);
    expect(states[0]).toMatchObject({ valueLabel: '5', count: 2, aggregation: 'max' });
  });

  it('independent: каждая Рана со своим значением — отдельная строка', () => {
    const rules: Rule[] = [stateRule(null, 'wound', 'Рана', { value_type: 'number', aggregation: 'independent' })];
    const states = serviceStates(rules, [
      { stateRuleCode: 'wound', value: 3 },
      { stateRuleCode: 'wound', value: 2 },
    ]);

    expect(states).toHaveLength(2);
    expect(states.map((s) => s.valueLabel)).toEqual(['3', '2']);
    expect(states.map((s) => s.id)).toEqual(['wound#0', 'wound#1']);
  });

  it('flag: значение не показывается, повторы объединяются', () => {
    const rules: Rule[] = [
      stateRule(null, 'unconscious', 'Потеря сознания', { value_type: 'flag', aggregation: 'max' }),
    ];
    const states = serviceStates(rules, [{ stateRuleCode: 'unconscious' }, { stateRuleCode: 'unconscious' }]);

    expect(states).toHaveLength(1);
    expect(states[0]).toMatchObject({ valueLabel: null, count: 2 });
  });

  it('неизвестное правило резолвится с флагом isResolved=false и не падает', () => {
    const states = serviceStates([], [{ stateRuleCode: 'rule-missing' }]);

    expect(states).toHaveLength(1);
    expect(states[0]).toMatchObject({ name: 'rule-missing', isResolved: false, href: '' });
  });

  it('отравление: каждая запись poison-блока — отдельная строка, имя/параметры из правила-яда', () => {
    const rules: Rule[] = [
      stateRule(null, 'poisoning', 'Отравление', {
        value_type: 'flag',
        aggregation: 'independent',
      }),
      damageTypeRule(null, 'poison-1', 'Яд 1 типа'),
      damageTypeRule(null, 'poison-3', 'Яд 3 типа'),
      poisonRule(null, 'poison-scorpion', 'Яд скорпиона', {
        default_strength: { base: 3, size: 1 },
      }),
      poisonRule(null, 'poison-viper', 'Яд гадюки', {
        damage_type_code: 'poison-3',
        default_strength: { base: 5, size: 0 },
        default_periodicity: { kind: 'literal', value: 3, step: 'turn' },
        default_decay: { kind: 'fixed', value: 2 },
      }),
    ];
    const states = serviceStates(rules, [
      { stateRuleCode: 'poisoning', poison: { poisonRuleCode: 'poison-scorpion' } },
      { stateRuleCode: 'poisoning', poison: { poisonRuleCode: 'poison-viper' } },
    ]);

    expect(states.map((s) => s.name)).toEqual(['Яд скорпиона', 'Яд гадюки']);
    expect(states.map((s) => s.id)).toEqual(['poisoning#poison-0', 'poisoning#poison-1']);
    expect(states.map((s) => s.valueLabel)).toEqual(['3↑', '5']);
    expect(states[0].dotLabel).toContain('3↑ яд 1 типа');
    expect(states[1].dotLabel).toContain('затухание 2');
  });

  it('отравление без правила-яда: имя из состояния «Отравление», параметры из записи', () => {
    const rules: Rule[] = [
      stateRule(null, 'poisoning', 'Отравление', {
        value_type: 'flag',
        aggregation: 'independent',
      }),
      damageTypeRule(null, 'poison-2', 'Яд 2 типа'),
    ];
    const states = serviceStates(rules, [
      {
        stateRuleCode: 'poisoning',
        poison: {
          poisonRuleCode: null,
          damage_type_code: 'poison-2',
          strength: { base: 7, size: 0 },
          periodicity: { kind: 'literal', value: 1, step: 'turn' },
          decay: { kind: 'fixed', value: 1 },
        },
      },
    ]);

    expect(states).toHaveLength(1);
    expect(states[0]).toMatchObject({ name: 'Отравление', valueLabel: '7', ruleCode: 'poisoning' });
    expect(states[0].dotLabel).toContain('яд 2 типа');
    expect(states[0].dotLabel).toContain('каждый ход');
  });
});

function serviceStates(rules: Rule[], states: CharacterStateValue[]) {
  return new CharacterOverviewService().build(version(states), rules).states;
}
