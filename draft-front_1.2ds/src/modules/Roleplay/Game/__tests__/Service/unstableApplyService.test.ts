import { describe, expect, it } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import { UnstableApplyService } from '@/modules/Roleplay/Game/Service/UnstableApplyService';

function state(code: string, flag: 'lying' | 'unstable'): Rule {
  return {
    id: null,
    code,
    type: 'state',
    name: code,
    description: '',
    spaceId: 1,
    mechanics: [],
    createdAt: 0,
    spec: { type: 'state', value_type: 'number', aggregation: 'sum', effects: [], [flag]: true },
  };
}

function dexterityCharacteristic(flagged: boolean): Rule {
  return {
    id: null,
    code: 'agility',
    type: 'characteristic',
    name: 'Ловкость',
    description: '',
    spaceId: 1,
    mechanics: [],
    createdAt: 0,
    spec: { type: 'characteristic', ...(flagged ? { unstable_roll: true } : {}) },
  };
}

function dexterityCheck(flagged: boolean): Rule {
  return {
    id: null,
    code: 'check-dexterity',
    type: 'check',
    name: 'Ловкость',
    description: '',
    spaceId: 1,
    mechanics: [],
    createdAt: 0,
    spec: {
      type: 'check',
      difficulty_input: { kind: 'ask' },
      allowed_modes: 'both',
      ...(flagged ? { unstable_check: true } : {}),
    },
  };
}

const rollRule: Rule = {
  id: null,
  code: 'roll',
  type: 'simple',
  name: 'Бросок',
  description: '',
  spaceId: 1,
  mechanics: [
    {
      mechanicId: 5,
      mechanicPayload: { type: 'roll', data: { sub_mechanics: [], efficiency: 3 } },
    },
  ],
  createdAt: 0,
};

const version = { states: [], characteristics: [] } as unknown as CharacterVersion;

function apiOf(): { api: IGameApi; added: string[] } {
  const added: string[] = [];
  const api = {
    async addCombatState(_gameId: number, _key: string, value: { stateRuleCode: string }) {
      added.push(value.stateRuleCode);

      return null;
    },
    async removeCombatState() {
      added.push('remove');

      return null;
    },
    async setCombatStateValue() {
      return null;
    },
  } as unknown as IGameApi;

  return { api, added };
}

describe('UnstableApplyService', () => {
  it('без unstable_check копит неустойчивость и не бросает', async () => {
    const { api, added } = apiOf();
    const service = new UnstableApplyService(() => api);
    const messages: string[] = [];
    await service.apply({
      gameId: 1,
      targetKey: 'character:1',
      version,
      rules: [state('wobble', 'unstable'), state('prone', 'lying'), dexterityCheck(false), rollRule],
      mechanics: [] as Mechanic[],
      amount: 2,
      targetName: 'Тест',
      chatId: 1,
      speaker: { kind: 'gm' },
      sendMessage: async (content) => {
        messages.push(content);

        return {};
      },
    });
    expect(added).toEqual(['wobble']);
    expect(messages).toEqual([]);
  });

  it('с unstable_check бросает и при провале кладёт лёжа', async () => {
    const { api, added } = apiOf();
    const service = new UnstableApplyService(() => api);
    const messages: string[] = [];
    await service.apply({
      gameId: 1,
      targetKey: 'character:1',
      version,
      rules: [
        state('wobble', 'unstable'),
        state('prone', 'lying'),
        dexterityCharacteristic(true),
        dexterityCheck(true),
        rollRule,
      ],
      mechanics: [] as Mechanic[],
      amount: 8,
      targetName: 'Тест',
      rng: () => 0,
      chatId: 1,
      speaker: { kind: 'gm' },
      sendMessage: async (content) => {
        messages.push(content);

        return {};
      },
    });
    expect(added[0]).toBe('wobble');
    expect(messages.some((line) => line.includes('Ловкость'))).toBe(true);
    expect(added).toContain('prone');
  });

  it('без unstable_roll копит неустойчивость и не бросает', async () => {
    const { api, added } = apiOf();
    const service = new UnstableApplyService(() => api);
    const messages: string[] = [];
    await service.apply({
      gameId: 1,
      targetKey: 'character:1',
      version,
      rules: [
        state('wobble', 'unstable'),
        state('prone', 'lying'),
        dexterityCharacteristic(false),
        dexterityCheck(true),
        rollRule,
      ],
      mechanics: [] as Mechanic[],
      amount: 8,
      targetName: 'Тест',
      rng: () => 0,
      chatId: 1,
      speaker: { kind: 'gm' },
      sendMessage: async (content) => {
        messages.push(content);

        return {};
      },
    });
    expect(added).toEqual(['wobble']);
    expect(messages).toEqual([]);
  });
});
