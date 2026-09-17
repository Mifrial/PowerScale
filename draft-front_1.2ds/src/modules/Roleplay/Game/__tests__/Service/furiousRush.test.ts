import { describe, expect, it } from 'vitest';
import { FuriousRushService } from '@/modules/Roleplay/Game/Service/FuriousRushService';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import { mockGameApi } from '@/modules/Roleplay/Game/Mock/mockGameApi';

const service = new FuriousRushService(() => mockGameApi as IGameApi);

const parent = {
  id: null,
  code: 'parent-sweep',
  type: 'ability',
  name: 'Размах',
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  createdAt: 1767225600,
  spec: {
    type: 'action',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [],
    action_effects: [
      {
        type: 'after_action_until_resource_spent_check_modifier',
        resource_code: 'action-points',
        amount: 2,
        check_codes: ['check-hit'],
        delta: -2,
      },
    ],
  },
} as Rule;

const child = {
  id: null,
  code: 'child-rush',
  type: 'ability',
  name: 'Рывок',
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  createdAt: 1767225600,
  spec: {
    type: 'skill',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: 'parent-sweep',
    action_effects: [
      {
        type: 'optional_after_strike_check',
        check_code: 'check-willpower',
        difficulty: 3,
        skip_parent_pending: true,
        self_damage: { size_delta: -1, damage_type_code: 'blunt', internal: true },
      },
    ],
  },
} as Rule;

describe('FuriousRushService', () => {
  it('находит опцию у взятого ребёнка родителя', () => {
    expect(service.optionsOf('parent-sweep', [{ ruleCode: 'child-rush', level: 1 }], [parent, child])).toHaveLength(1);
    expect(service.optionsOf('parent-sweep', [], [parent, child])).toHaveLength(0);
  });

  it('сила удара↓ на шкале 3–5 даёт −1 размер', () => {
    expect(service.selfDamage({ base: 5, size: 0 }, -1)).toEqual({ base: 5, size: -1 });
  });

  it('без выбранной опции не снимает pending', async () => {
    const version = {
      abilities: [{ ruleCode: 'child-rush', level: 1 }],
      inventory: [],
      characteristics: [],
      states: [],
      resources: [],
    } as unknown as CharacterVersion;
    const result = await service.apply({
      gameId: 1,
      actorKey: 'character:1',
      version,
      rules: [parent, child],
      mechanics: [],
      actionRule: parent,
      attack: { itemRuleCode: 'staff', profileType: 'strike', damageTypeCode: 'slashing' },
      selectedChildCodes: [],
      actorName: 'Тест',
      chatId: null,
      speaker: { kind: 'character', characterId: 1, characterName: 'Тест' },
      sendMessage: async () => undefined,
    });
    expect(result.skipPending).toBe(false);
  });

  it('в чат сначала бросок, потом текст', async () => {
    const version = {
      abilities: [{ ruleCode: 'child-rush', level: 1 }],
      inventory: [],
      characteristics: [{ ruleCode: 'willpower', value: { base: 3, size: 0 } }],
      states: [],
      resources: [],
    } as unknown as CharacterVersion;
    const sent: { content: string; attachments: unknown[] }[] = [];
    await service.apply({
      gameId: 1,
      actorKey: 'character:1',
      version,
      rules: [parent, child],
      mechanics: [],
      actionRule: parent,
      attack: { itemRuleCode: 'staff', profileType: 'strike', damageTypeCode: 'slashing' },
      selectedChildCodes: ['child-rush'],
      actorName: 'Тест',
      rng: () => 0.99,
      chatId: 1,
      speaker: { kind: 'character', characterId: 1, characterName: 'Тест' },
      sendMessage: async (content, attachments) => {
        sent.push({ content, attachments });
      },
    });
    expect(sent).toHaveLength(2);
    expect(sent[0]?.content).toBe('');
    expect(sent[0]?.attachments).toHaveLength(1);
    expect(sent[1]?.content).toContain('проходит проверку на Рывок');
    expect(sent[1]?.attachments).toEqual([]);
  });
});
