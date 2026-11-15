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

  it('сохраняет авторитетное value и delta характеристики из sheet PHP', () => {
    const version = characterHttpViewService.versionFromStored({
      name: 'Герой',
      rulesRevision: 1,
      choices: { name: 'Герой', raceCode: 'human', abilities: [] },
      sheet: {
        characteristics: [
          { characteristicCode: 'perception', base: dim(3), delta: 3, value: dim(3, 1), modifiers: [] },
          { characteristicCode: 'strength', base: dim(3), delta: 0, value: dim(3), modifiers: [] },
        ],
      },
    });

    const perception = version?.characteristics.find((row) => row.ruleCode === 'perception');
    expect(perception?.base).toEqual(dim(3));
    expect(perception?.value).toEqual(dim(3, 1));
    expect(perception?.delta).toBe(3);
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

  it('снимок NPC сохраняет мощь ядра, донора и цели формы способности', () => {
    const version = characterHttpViewService.versionFromStored({
      spaceCode: 'razrabotka',
      rulesRevision: 50,
      choices: {
        name: 'Колдунец',
        raceCode: 'kogir',
        abilities: [{ ruleCode: 'discharge', level: 1, zone: 'or', targets: { core: 5 } }],
        inventory: [
          {
            id: 5,
            ruleCode: 'magic-core',
            quantity: 1,
            equipped: true,
            parameters: { power: dim(4) },
            grantedBy: { ruleCode: 'magic-core-capacity' },
            payload: { volume: 30, recorded_spells: [{ ruleCode: 'spark', level: 1 }] },
          },
        ],
      },
      sheet: { money: 0, resources: [] },
    });

    expect(version?.inventory[0]).toMatchObject({
      id: 5,
      parameters: { power: dim(4) },
      grantedBy: { ruleCode: 'magic-core-capacity' },
      payload: { volume: 30, recorded_spells: [{ ruleCode: 'spark', level: 1 }] },
    });
    expect(version?.abilities[0]?.targets).toEqual({ core: 5 });
  });

  it('снимок NPC отдаёт ману и Объём кристалла', () => {
    const version = characterHttpViewService.versionFromStored({
      spaceCode: 'razrabotka',
      rulesRevision: 51,
      choices: { name: 'Колдунец', raceCode: 'kogir', abilities: [], inventory: [] },
      sheet: {
        money: 0,
        resources: [],
        items: {
          'inventory:5': {
            ruleCode: 'magic-crystal',
            inventoryId: 5,
            maxPower: dim(4),
            volume: 30,
            occupied: 10,
            mana: 12,
            manaMax: 20,
          },
        },
      },
    });

    expect(version?.items).toEqual([
      {
        inventoryId: 5,
        ruleCode: 'magic-crystal',
        maxPower: dim(4),
        volume: 30,
        occupied: 10,
        mana: 12,
        manaMax: 20,
      },
    ]);
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

  it('инвентарь сохраняет выбранную занятость рук и модификаторы', () => {
    const version = characterHttpViewService.versionOf({
      name: 'Тестовичок',
      spaceCode: '',
      rulesRevision: 17,
      choices: {
        name: 'Тестовичок',
        inventory: [
          {
            id: 8,
            ruleCode: 'polutornyy-mech',
            quantity: 1,
            equipped: true,
            occupyHands: 2,
            modifiers: ['stalnaya-okovka'],
            custom: { name: 'Меч', description: 'Наследие' },
          },
        ],
      },
      sheet: {},
    });

    expect(version.inventory).toEqual([
      {
        id: 8,
        ruleCode: 'polutornyy-mech',
        quantity: 1,
        equipped: true,
        occupyHands: 2,
        name: 'Меч',
        description: 'Наследие',
        modifierRuleCodes: ['stalnaya-okovka'],
      },
    ]);
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
            bonuses: [
              { sourceRuleCode: 'dexterity_source', delta: 1 },
              { sourceRuleCode: 'perception_source', delta: 0 },
            ],
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

describe('CharacterHttpViewService.detailOf', () => {
  it('переносит метаданные и разрешённые секции из плоского PHP-ответа', () => {
    const detail = characterHttpViewService.detailOf({
      id: 3,
      name: 'Воюн',
      ownerId: 7,
      ownerName: 'Игрок',
      status: 'draft',
      spaceId: 1,
      spaceCode: 'world',
      revision: 4,
      active: false,
      raceLabel: 'Эльф',
      shortDescription: 'Кратко',
      visibleSections: ['states', 'characteristics'],
      choices: { name: 'Воюн', raceCode: 'elf' },
      sheet: { states: [{ id: 1, stateRuleCode: 'stunned' }] },
    });

    expect(detail.character.ownerName).toBe('Игрок');
    expect(detail.character.status).toBe('draft');
    expect(detail.character.spaceCode).toBe('world');
    expect(detail.character.raceLabel).toBe('Эльф');
    expect(detail.character.shortDescription).toBe('Кратко');
    expect(detail.visibleSections).toEqual(['states', 'characteristics']);
    expect(detail.version.states).toEqual([{ id: 1, stateRuleCode: 'stunned' }]);
  });

  it('не подменяет отсутствующие секции пустым списком', () => {
    const detail = characterHttpViewService.detailOf({
      id: 3,
      name: 'Воюн',
      spaceId: 1,
      revision: 4,
      active: true,
      choices: { name: 'Воюн' },
      sheet: {},
    });

    expect(detail.visibleSections).toBeUndefined();
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

describe('CharacterHttpViewService: sourcePowers', () => {
  it('читает эффективную мощь источников из sheet', () => {
    const version = characterHttpViewService.versionOf({
      name: 'Колдунец',
      spaceCode: 'razrabotka',
      rulesRevision: 50,
      choices: { name: 'Колдунец', abilities: [], inventory: [] },
      sheet: {
        sourcePowers: [
          {
            inventoryId: 5,
            ruleCode: 'magic-core',
            characteristicCode: 'magic-power',
            base: { base: 4, size: 0 },
            bonus: 1,
            value: { base: 5, size: 0 },
            contributions: [{ sourceRuleCode: 'core-flow-optimization', delta: 1 }],
          },
        ],
      },
    });

    expect(version.sourcePowers).toEqual([
      {
        inventoryId: 5,
        ruleCode: 'magic-core',
        characteristicCode: 'magic-power',
        base: { base: 4, size: 0 },
        bonus: 1,
        value: { base: 5, size: 0 },
        contributions: [{ sourceRuleCode: 'core-flow-optimization', delta: 1 }],
      },
    ]);
  });
});
