import { describe, expect, it } from 'vitest';
import { characterHttpViewService } from '@/modules/Roleplay/Character/Service/Instance/characterHttpViewService';
import { CharacterApiError } from '@/modules/Roleplay/Character/Service/CharacterApiError';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

const dim = (base: number, size = 0) => ({ base, size });

const rule = (code: string, type: Rule['type'], name: string, spec?: Rule['spec']): Rule => ({
  id: null,
  code,
  type,
  name,
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanics: [],
  createdAt: 0,
  spec,
});

describe('CharacterHttpViewService.versionFromStored', () => {
  it('PHP-снимок membership даёт CharacterVersion с массивами', () => {
    const version = characterHttpViewService.versionFromStored({
      name: 'Герой',
      rulesRevision: 1,
      choices: { name: 'Герой', raceCode: 'human', abilities: [] },
      sheet: { characteristicPurchases: [{ characteristicCode: 'strength', cost: 2 }] },
    });

    expect(version).not.toBeNull();
    expect(Array.isArray(version?.characteristics)).toBe(true);
    expect(version?.raceRuleCode).toBe('human');
    expect(version?.rulesRevision).toBe(1);
  });

  it('читает расу из choices.race, если raceCode нет', () => {
    const version = characterHttpViewService.versionFromStored({
      name: 'Герой',
      rulesRevision: 1,
      choices: { name: 'Герой', race: 'human' },
      sheet: {},
    });

    expect(version?.raceRuleCode).toBe('human');
  });

  it('снимок NPC (choices/sheet без characteristics) даёт те же массивы листа', () => {
    const version = characterHttpViewService.versionFromStored({
      spaceCode: 'razrabotka',
      rulesRevision: 3,
      choices: { name: 'Тестовичок', raceCode: '', abilities: [], inventory: [] },
      sheet: { money: 0, resources: [] },
    });

    expect(version?.inventory).toEqual([]);
    expect(version?.abilities).toEqual([]);
    expect(version?.spaceCode).toBe('razrabotka');
    expect(version?.points.orTotal).toBeNull();
    expect(version?.budgets?.osTotal).toBeNull();
  });

  it('уже собранную CharacterVersion не ломает', () => {
    const current = characterHttpViewService.versionOf({
      name: 'Герой',
      spaceCode: 'razrabotka',
      rulesRevision: 2,
      choices: { name: 'Герой' },
      sheet: {},
    });
    const roundTrip = characterHttpViewService.versionFromStored(current);

    expect(roundTrip).toBe(current);
  });
});

describe('CharacterHttpViewService.versionOf', () => {
  it('game.getSheets: закупки с value дают ruleCode, base и characteristicPurchases', () => {
    const version = characterHttpViewService.versionOf({
      name: 'Воюн',
      spaceCode: '',
      rulesRevision: 3,
      choices: {
        name: 'Воюн',
        raceCode: 'human',
        characteristicPurchases: [{ characteristicCode: 'awareness', cost: 2 }],
      },
      sheet: {
        characteristicPurchases: [
          { characteristicCode: 'awareness', cost: 2, value: { base: 1, size: 0 } },
          { characteristicCode: 'strength', cost: 2, value: { base: 8, size: 0 } },
        ],
      },
    });

    expect(version.characteristicPurchases).toEqual([
      { characteristicCode: 'awareness', cost: 2 },
      { characteristicCode: 'strength', cost: 2 },
    ]);
    expect(version.characteristics).toEqual([
      { ruleCode: 'awareness', base: { base: 1, size: 0 }, modifiers: [] },
      { ruleCode: 'strength', base: { base: 8, size: 0 }, modifiers: [] },
    ]);
    expect(version.raceRuleCode).toBe('human');
  });
});

describe('CharacterHttpViewService.materialize', () => {
  it('PHP-ступень закупки не подменяет итог с врождённым и размером', () => {
    const rules: Rule[] = [
      rule('strength', 'characteristic', 'Сила', { type: 'characteristic', group: 'primary' }),
      rule('innate', 'source', 'Врождённое'),
      rule('from-size', 'source', 'От размера'),
      rule('orgul', 'race', 'Оргул', {
        parent_race_code: null,
        cost_os: 2,
        characteristics: [
          {
            characteristic_code: 'strength',
            mode: 'purchased',
            base: dim(3, 1),
            purchase: [{ cost: 2, value: dim(3, 1) }],
          },
        ],
        abilities: [{ ability_code: 'size-brute', automatic: true }],
      }),
      rule('size-brute', 'ability', 'Бугай', {
        type: 'trait',
        zones: { os: { kind: 'automatic' } },
        requirements: [],
        grants: [
          {
            level: 1,
            grants: [
              {
                type: 'characteristic_modify',
                characteristic_code: 'strength',
                amount: { type: 'fixed', value: 2 },
                source_code: 'from-size',
              },
            ],
          },
        ],
        parent_ability_code: null,
      }),
      rule('innate-strength', 'ability', 'Врождённая сила', {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [2, 4, 8] } },
        requirements: [],
        grants: [
          {
            level: 1,
            grants: [
              {
                type: 'characteristic_modify',
                characteristic_code: 'strength',
                amount: { type: 'parameter', parameter_code: 'x', per_unit: 1 },
                source_code: 'innate',
              },
            ],
          },
        ],
        parent_ability_code: null,
      }),
    ];
    const stored = characterHttpViewService.versionFromStored({
      spaceCode: 'aktualnye-pravila',
      rulesRevision: 5,
      choices: {
        name: 'Тестовичок',
        raceCode: 'orgul',
        characteristicPurchases: [{ characteristicCode: 'strength', cost: 2 }],
        abilities: [{ ruleCode: 'innate-strength', level: 1, parameters: { x: { base: 1, size: 0 } } }],
      },
      sheet: {
        characteristicPurchases: [{ characteristicCode: 'strength', cost: 2, value: { base: 5, size: 1 } }],
      },
    });
    expect(stored).not.toBeNull();
    expect(stored?.characteristics[0]).toEqual({
      ruleCode: 'strength',
      base: { base: 5, size: 1 },
      modifiers: [],
    });

    const live = characterHttpViewService.materialize(stored!, 1, rules);
    const strength = live.characteristics.find((row) => row.ruleCode === 'strength');
    const summed = strength!.modifiers.reduce((sum, row) => sum + row.delta, 0);

    expect(new DimensionalNumber(strength!.base).toString()).toBe('3↑');
    expect(strength!.modifiers.map((row) => row.delta).sort((a, b) => a - b)).toEqual([1, 2]);
    expect(new DimensionalNumber(strength!.base).modify(summed, { min: 3, max: 5 }).toString()).toBe('3↑²');
  });

  it('читает базу и bonuses лимита ресурса с PHP sheet', () => {
    const version = characterHttpViewService.versionOf({
      name: 'Воюн',
      spaceCode: 'aktualnye-pravila',
      rulesRevision: 1,
      choices: { name: 'Воюн' },
      sheet: {
        resources: [
          {
            ruleCode: 'action-points',
            current: 6,
            base: 5,
            bonuses: [{ sourceRuleCode: 'dexterity_source', delta: 1 }, { sourceRuleCode: 'perception_source', delta: 0 }],
          },
        ],
      },
    });

    expect(version.resources[0]).toEqual({
      ruleCode: 'action-points',
      current: dim(6),
      base: dim(5),
      bonuses: [{ sourceRuleCode: 'dexterity_source', sourceLabel: null, delta: 1 }],
    });
  });

  it('materializeKeepingRuntime не затирает PHP current авто-ресурса', () => {
    const rules: Rule[] = [
      rule('action-points', 'resource', 'Очки Действий', {
        is_dimensional: false,
        auto_add: true,
        limit: { base: 6, adjustments: [] },
      }),
    ];
    const stored = characterHttpViewService.versionOf({
      name: 'Воюн',
      spaceCode: 'aktualnye-pravila',
      rulesRevision: 1,
      choices: { name: 'Воюн' },
      sheet: { resources: [{ ruleCode: 'action-points', current: 1 }] },
    });
    const live = characterHttpViewService.materializeKeepingRuntime(stored, 1, rules);
    const actionPoints = live.resources.find((row) => row.ruleCode === 'action-points');

    expect(actionPoints?.current).toEqual({ base: 1, size: 0 });
  });
});

describe('CharacterHttpViewService.migratedDetailOf', () => {
  it('conflicts бросает CHARACTER_INVALID с текстами problems', () => {
    expect(() =>
      characterHttpViewService.migratedDetailOf({
        kind: 'conflicts',
        problems: [
          { code: 'CHARACTER_RACE', message: 'Раса не собралась', stage: 'assemble' },
          { code: 'CHARACTER_LIMIT', message: 'Предел ОС', stage: 'assemble' },
        ],
      }),
    ).toThrow(CharacterApiError);
    try {
      characterHttpViewService.migratedDetailOf({
        kind: 'conflicts',
        problems: [{ code: 'CHARACTER_RACE', message: 'Раса не собралась', stage: 'assemble' }],
      });
    } catch (error) {
      expect(error).toBeInstanceOf(CharacterApiError);
      expect((error as CharacterApiError).code).toBe('CHARACTER_INVALID');
      expect((error as CharacterApiError).message).toBe('Раса не собралась');
    }
  });

  it('ok собирает CharacterDetail', () => {
    const detail = characterHttpViewService.migratedDetailOf({
      kind: 'ok',
      id: 3,
      name: 'Воюн',
      spaceId: 1,
      revision: 4,
      active: true,
      actualVersion: 5,
      choices: { name: 'Воюн', raceCode: 'human' },
      sheet: {},
    });

    expect(detail.character.id).toBe(3);
    expect(detail.character.rulesRevision).toBe(4);
    expect(detail.actualVersion).toBe(5);
  });
});
