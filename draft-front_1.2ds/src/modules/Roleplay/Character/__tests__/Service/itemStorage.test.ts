import { describe, expect, it } from 'vitest';
import { ItemStorageService } from '@/modules/Roleplay/Character/Service/ItemStorageService';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { ItemStorage } from '@/modules/Roleplay/Rule/Dto/Item/ItemStorage';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const service = new ItemStorageService();

function spellRule(code: string, cost: number, type: 'spell' | 'action' = 'spell', keywordIds: number[] = []): Rule {
  const spec =
    type === 'spell'
      ? ({
          type: 'spell',
          zones: { or: { kind: 'array', levels_cost: [cost] } },
          requirements: [],
          grants: [],
          parent_ability_code: null,
          action_components: [],
          hit_resolution: { type: 'none' },
          spell: {
            power: { base: 4, size: 0 },
            control: { base: 3, size: 0 },
            duration: { type: 'instant' },
          },
        } as Extract<AbilitySpec, { type: 'spell' }>)
      : ({ type: 'action', zones: { or: { kind: 'array', levels_cost: [cost] } } } as unknown as AbilitySpec);

  return {
    id: null,
    code,
    type: 'ability',
    name: code,
    description: '',
    spaceId: 1,
    keywordIds,
    mechanics: [],
    spec,
    createdAt: 1,
  } as Rule;
}

function upgradeRule(code: string, parent: string | null, extra: Record<string, unknown> = {}): Rule {
  return {
    id: null,
    code,
    type: 'ability',
    name: code,
    description: '',
    spaceId: 1,
    keywordIds: [],
    mechanics: [],
    spec: {
      type: 'skill',
      zones: { or: { kind: 'array', levels_cost: [1] } },
      requirements: [],
      grants: [],
      parent_ability_code: parent,
      spell_upgrade: { action_point_delta: 1, ...extra },
    },
    createdAt: 1,
  } as unknown as Rule;
}

const storage: ItemStorage = {
  power_parameter: 'max_power',
  volume_parameter: 'volume',
  recorded_parameter: 'recorded_spells',
  volume_base: 20,
  volume_increment: 10,
  min_volume_power_delta: 3,
};

describe('ItemStorageService', () => {
  it('стоимость в объём растёт удвоением', () => {
    expect(service.volumeOfCost(1)).toBe(10);
    expect(service.volumeOfCost(2)).toBe(20);
    expect(service.volumeOfCost(3)).toBe(40);
    expect(service.volumeOfCost(4)).toBe(80);
    expect(service.volumeOfCost(0)).toBe(0);
  });

  it('занятый объём складывает заклинания и улучшения', () => {
    const rules = [spellRule('lightning-strike', 2), spellRule('chain-lightning', 3)];

    expect(service.occupied([{ ruleCode: 'lightning-strike' }], rules)).toBe(20);
    expect(service.occupied([{ ruleCode: 'lightning-strike', upgrades: ['chain-lightning'] }], rules)).toBe(60);
    expect(service.occupied([], rules)).toBe(0);
  });

  it('лимит маны — свободный объём', () => {
    expect(service.manaLimit(40, 20)).toBe(20);
    expect(service.manaLimit(20, 40)).toBe(0);
  });

  it('запись не влезает — состав не меняется', () => {
    const rules = [spellRule('lightning-strike', 2)];

    expect(service.withRecorded([], 'lightning-strike', 20, rules)).toEqual([{ ruleCode: 'lightning-strike' }]);
    expect(service.withRecorded([], 'lightning-strike', 10, rules)).toEqual([]);
    expect(service.withRecorded([{ ruleCode: 'lightning-strike' }], 'lightning-strike', 40, rules)).toEqual([
      { ruleCode: 'lightning-strike' },
    ]);
  });

  it('читает записи из payload и отбрасывает чужие строки', () => {
    expect(
      service.recordedOf(
        {
          volume: 40,
          recorded_spells: [
            { ruleCode: 'lightning-strike' },
            { ruleCode: 'chain-lightning', upgrades: ['power-upgrade', 5] },
            { upgrades: [] },
          ],
        },
        storage,
      ),
    ).toEqual([{ ruleCode: 'lightning-strike' }, { ruleCode: 'chain-lightning', upgrades: ['power-upgrade'] }]);
    expect(service.recordedOf({ volume: 40 }, storage)).toEqual([]);
  });

  it('recordableSpells отбирает только заклинания с платной зоной', () => {
    const rules = [spellRule('lightning-strike', 2), spellRule('strike', 1, 'action'), spellRule('free', 0)];

    expect(service.recordableSpells(rules).map((rule) => rule.code)).toEqual(['lightning-strike']);
  });

  it('recordableSpells соблюдает ограничение хранилища по признаку', () => {
    const rules = [spellRule('lightning-strike', 2, 'spell', [50]), spellRule('magic-perception', 2, 'spell', [2])];
    const keywordCodeById = new Map([
      [50, 'electromancy'],
      [2, 'action'],
    ]);

    expect(
      service
        .recordableSpells(rules, { ...storage, recorded_keywords: ['electromancy'] }, keywordCodeById)
        .map((rule) => rule.code),
    ).toEqual(['lightning-strike']);
    expect(service.recordableSpells(rules, storage, keywordCodeById).map((rule) => rule.code)).toEqual([
      'lightning-strike',
      'magic-perception',
    ]);
  });

  it('улучшения записи: привязанные к заклинанию и без цепи', () => {
    const rules = [
      spellRule('lightning-strike', 2),
      upgradeRule('power-upgrade', 'lightning-strike'),
      upgradeRule('chained', 'lightning-strike', { chain: { damage_size_per_hop: 1 } }),
      upgradeRule('foreign', 'other-spell'),
      upgradeRule('any-path', null),
    ];

    expect(service.applicableUpgrades('lightning-strike', rules).map((rule) => rule.code)).toEqual(['power-upgrade']);
  });

  it('улучшение добавляется, если влезает, и убирается', () => {
    const rules = [spellRule('lightning-strike', 2), upgradeRule('power-upgrade', 'lightning-strike')];
    const recorded = [{ ruleCode: 'lightning-strike' }];

    expect(service.withUpgrade(recorded, 'lightning-strike', 'power-upgrade', 20, rules)).toEqual([
      { ruleCode: 'lightning-strike' },
    ]);
    expect(service.withUpgrade(recorded, 'lightning-strike', 'power-upgrade', 30, rules)).toEqual([
      { ruleCode: 'lightning-strike', upgrades: ['power-upgrade'] },
    ]);
    expect(
      service.withUpgrade(
        [{ ruleCode: 'lightning-strike', upgrades: ['power-upgrade'] }],
        'lightning-strike',
        'power-upgrade',
        40,
        rules,
      ),
    ).toEqual([{ ruleCode: 'lightning-strike', upgrades: ['power-upgrade'] }]);
    expect(
      service.withoutUpgrade(
        [{ ruleCode: 'lightning-strike', upgrades: ['power-upgrade'] }],
        'lightning-strike',
        'power-upgrade',
      ),
    ).toEqual([{ ruleCode: 'lightning-strike' }]);
  });

  it('withoutRecorded убирает запись по коду', () => {
    expect(service.withoutRecorded([{ ruleCode: 'a' }, { ruleCode: 'b' }], 'a')).toEqual([{ ruleCode: 'b' }]);
  });
});
