import { afterEach, describe, expect, it } from 'vitest';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { registerGameApi } from '@/modules/Roleplay/Game/init';
import { endOfTurnDotsService } from '@/modules/Roleplay/Game/Service/Instance/endOfTurnDotsService';

import { mockGameApi } from '@/modules/Roleplay/Game/Mock/mockGameApi';

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

const accumulatedDamage: Rule = {
  id: 'rule-damage',
  code: 'accumulated-damage',
  type: 'state',
  name: 'Повреждения',
  description: '',
  spaceId: 1,
  spec: { value_type: 'dimensional', aggregation: 'sum', effects: [] },
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
    default_strength: { base: 1, size: 0 },
    default_periodicity: { kind: 'literal', value: 2, step: 'turn' },
    default_decay: { kind: 'fixed', value: 1 },
  },
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
};

function versionOf(states: CharacterStateValue[]): CharacterVersion {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'x',
    rulesRevision: 1,
    raceRuleId: null,
    characteristics: [],
    resources: [],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: null },
    money: 0,
    ageYears: null,
    inventory: [],
    states,
    senses: [],
  };
}

function overlayOf(states: CharacterStateValue[]): GameCombatOverlay {
  return {
    gameId: 1,
    entityKey: 'character:1',
    kind: 'character',
    resources: [],
    states: states.map((state) => ({ ...state })),
    updatedAt: 't',
  };
}

describe('applyEndOfTurnDots', () => {
  afterEach(() => {
    registerGameApi(mockGameApi);
  });

  it('горение тикает каждый ход; яд period 2 на первом ходе только ждёт', async () => {
    const states: CharacterStateValue[] = [
      { stateRuleId: 'rule-burn', dimensionalValue: { base: 2, size: 0 } },
      { stateRuleId: 'rule-poisoning', poison: { poisonRuleId: 'rule-scorpion' } },
      { stateRuleId: 'rule-damage', dimensionalValue: { base: 3, size: 0 } },
    ];
    const api = {
      replaceCombatState: async (_g: number, _k: string, index: number, state: CharacterStateValue) => {
        states[index] = state;

        return overlayOf(states);
      },
      removeCombatState: async (_g: number, _k: string, index: number) => {
        states.splice(index, 1);

        return overlayOf(states);
      },
      addCombatState: async (_g: number, _k: string, state: CharacterStateValue) => {
        states.push(state);

        return overlayOf(states);
      },
      setCombatStateValue: async (_g: number, _k: string, index: number, value?: number) => {
        states[index] = { ...states[index], value };

        return overlayOf(states);
      },
    };
    registerGameApi(api as unknown as IGameApi);
    const chat: string[] = [];
    await endOfTurnDotsService.applyEndOfTurnDots({
      version: versionOf(states),
      endurance: 99,
      rules: [burning, poisoning, scorpion, accumulatedDamage],
      mechanics: [],
      gameId: 1,
      targetKey: 'character:1',
      targetName: 'Тест',
      chatId: 1,
      speaker: { kind: 'gm' },
      sendMessage: async (content) => {
        if (content) chat.push(content);

        return true;
      },
    });
    expect(states[0].dotTurnsLeft).toBe(1);
    expect(states[1].dotTurnsLeft).toBe(1);
    expect(chat.some((line) => line.includes('получает'))).toBe(true);
    expect(chat.some((line) => line.includes('скорпиона'))).toBe(false);
    expect(states[2]?.dimensionalValue).toEqual({ base: 5, size: 0 });
  });
});
