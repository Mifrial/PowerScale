import { describe, it, expect } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RaceCharacteristic } from '@/modules/Roleplay/Rule/Dto/Race/RaceCharacteristic';
import { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';
import { CharacterBuildService } from '@/modules/Roleplay/Character/Service/CharacterBuildService';

const dim = (base: number, size = 0) => ({ base, size });

const base = (id: string, code: string, type: Rule['type'], name: string, spec?: Rule['spec']): Rule => ({
  id,
  code,
  type,
  name,
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
  spec,
});

/** Раса с фиксированными характеристиками (для детерминированных тестов лимита ОД). */
const raceWith = (characteristics: RaceCharacteristic[]): Rule =>
  base('r-human', 'human', 'race', 'Человек', {
    parent_race_code: null,
    cost_os: 0,
    characteristics,
    abilities: [],
  });

const rules: Rule[] = [
  base('r-strength', 'strength', 'characteristic', 'Сила', { type: 'characteristic', group: 'primary' }),
  base('r-dexterity', 'dexterity', 'characteristic', 'Ловкость', { type: 'characteristic', group: 'primary' }),
  base('r-attention', 'attention', 'characteristic', 'Внимательность', {
    type: 'characteristic',
    group: 'base',
    automatic: true,
  }),
  base('r-reaction', 'reaction', 'characteristic', 'Реакция', {
    type: 'characteristic',
    group: 'base',
    automatic: true,
  }),
  base('r-perception', 'perception', 'characteristic', 'Восприятие', {
    type: 'characteristic',
    formula: 'min(attention, reaction)',
    group: 'primary',
  }),
  base('r-weight', 'weight', 'characteristic', 'Вес', {
    type: 'characteristic',
    group: 'primary',
    automatic: true,
    base_from: { characteristic_code: 'strength', source_codes: ['innate'] },
  }),
  base('r-innate', 'innate', 'source', 'Врождённый'),
  base('r-training', 'training', 'source', 'Тренировка'),
  base('r-ap', 'action-points', 'resource', 'Очки Действий', {
    is_dimensional: false,
    auto_add: true,
    limit: {
      base: 5,
      adjustments: [
        { value: { type: 'characteristic_size', characteristic_code: 'dexterity' }, source_code: 'innate' },
        { value: { type: 'characteristic_size', characteristic_code: 'perception' }, source_code: 'innate' },
        {
          value: {
            type: 'characteristic_size_gap',
            characteristic_code_from: 'strength',
            characteristic_code_to: 'weight',
          },
          source_code: 'innate',
        },
      ],
    },
  }),
  raceWith([
    { characteristic_code: 'strength', mode: 'fixed', base: dim(3) },
    { characteristic_code: 'dexterity', mode: 'fixed', base: dim(3) },
    { characteristic_code: 'attention', mode: 'fixed', base: dim(3) },
    { characteristic_code: 'reaction', mode: 'fixed', base: dim(3) },
  ]),
  base('r-strength-innate', 'strength-innate', 'ability', 'Врождённая Сила', {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [2] } },
    requirements: [],
    grants: [
      {
        level: 1,
        grants: [
          {
            type: 'characteristic_modify',
            characteristic_code: 'strength',
            amount: { type: 'fixed', value: 1 },
            source_code: 'innate',
          },
        ],
      },
    ],
    parent_ability_code: null,
  }),
  base('r-strength-training', 'strength-training', 'ability', 'Тренировка силы', {
    type: 'trait',
    zones: { or: { kind: 'array', levels_cost: [3] } },
    requirements: [],
    grants: [
      {
        level: 1,
        grants: [
          {
            type: 'characteristic_modify',
            characteristic_code: 'strength',
            amount: { type: 'fixed', value: 3 },
            source_code: 'training',
          },
        ],
      },
    ],
    parent_ability_code: null,
  }),
  base('r-costly', 'costly', 'ability', 'Затратный навык', {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [2] } },
    requirements: [{ level: 1, requirements: [{ type: 'resource_limit', resource_code: 'action-points', min: 2 }] }],
    grants: [],
    parent_ability_code: null,
  }),
];

const config: CharacterCreationConfig = { osTotal: 10, orTotal: 20, moneyBudget: 100 };

const service = new CharacterEditorService();
const buildService = new CharacterBuildService(service);

function makeBuild(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    spaceId: 1,
    raceRuleId: 'r-human',
    characteristicPurchases: [],
    abilities: [],
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
    ...overrides,
  };
}

function odOf(version: ReturnType<CharacterEditorService['toVersion']>) {
  return version.resources.find((resource) => resource.ruleId === 'r-ap');
}

describe('Авто-ресурс ОД (buildResources)', () => {
  it('авто-добавляется с базой 5 и условиями по характеристикам', () => {
    const version = service.toVersion(makeBuild(), rules, config);
    const od = odOf(version);

    expect(od).toBeDefined();
    expect(od?.base).toEqual({ base: 5, size: 0 });
    // Ловкость {3|0}, Восприятие min(3,3)={3|0}, разница Сила−Вес 0 → лимит 5.
    expect(od?.bonuses).toHaveLength(3);
    expect(od?.bonuses.map((bonus) => bonus.delta)).toEqual([0, 0, 0]);
    expect(od?.current).toEqual({ base: 5, size: 0 });
  });

  it('размер Ловкости выше среднего добавляет к лимиту', () => {
    const rulesWithDex: Rule[] = rules.map((rule) =>
      rule.code === 'human'
        ? raceWith([
            { characteristic_code: 'strength', mode: 'fixed', base: dim(3) },
            { characteristic_code: 'dexterity', mode: 'fixed', base: dim(3, 1) },
            { characteristic_code: 'attention', mode: 'fixed', base: dim(3) },
            { characteristic_code: 'reaction', mode: 'fixed', base: dim(3) },
          ])
        : rule,
    );
    const version = service.toVersion(makeBuild(), rulesWithDex, config);
    const od = odOf(version);

    expect(od?.bonuses[0].delta).toBe(1);
    expect(od?.current).toEqual({ base: 6, size: 0 });
  });

  it('разница Сила−Вес: +1 за полный размер, на который Сила выше Веса', () => {
    // Врождённая Сила (+1, врождённый) меняет и Силу, и Вес; Тренировка силы (+3, «Тренировка»)
    // меняет только Силу → Сила на полный размер выше Веса.
    let build = makeBuild();
    build = buildService.setAbilityLevel(build, 'r-strength-innate', 1, rules);
    build = buildService.setAbilityLevel(build, 'r-strength-training', 1, rules);
    const version = service.toVersion(build, rules, config);
    const od = odOf(version);

    expect(version.characteristics.find((c) => c.ruleId === 'r-weight')?.base).toEqual({ base: 4, size: 0 });
    expect(od?.bonuses[2].delta).toBe(1);
    expect(od?.current).toEqual({ base: 6, size: 0 });
  });

  it('floor 0: лимит ОД не уходит в минус', () => {
    const rulesWithLowDex: Rule[] = rules.map((rule) =>
      rule.code === 'human'
        ? raceWith([
            { characteristic_code: 'strength', mode: 'fixed', base: dim(3) },
            { characteristic_code: 'dexterity', mode: 'fixed', base: dim(3, -6) },
            { characteristic_code: 'attention', mode: 'fixed', base: dim(3) },
            { characteristic_code: 'reaction', mode: 'fixed', base: dim(3) },
          ])
        : rule,
    );
    const version = service.toVersion(makeBuild(), rulesWithLowDex, config);
    const od = odOf(version);

    expect(od?.current).toEqual({ base: 0, size: 0 });
    // База лимита в версии остаётся честной (5), лимит считается как max(0, база + бонусы).
    expect(od?.base).toEqual({ base: 5, size: 0 });
  });

  it('resource_limit требование на ОД проходит (лимит из авто-ресурса)', () => {
    const model = service.build(makeBuild(), rules, config);
    const costly = model.abilities.find((ability) => ability.code === 'costly');

    expect(costly?.levels[0].met).toBe(true);
  });

  it('не-авто ресурсы из build сохраняются как есть', () => {
    const build = makeBuild({ resources: [{ ruleId: 'r-concentration', current: dim(2), base: dim(3), bonuses: [] }] });
    const version = service.toVersion(build, rules, config);
    const concentration = version.resources.find((resource) => resource.ruleId === 'r-concentration');

    expect(concentration).toEqual({ ruleId: 'r-concentration', current: dim(2), base: dim(3), bonuses: [] });
  });
});

describe('Вес (base_from)', () => {
  it('база = база Силы + модификаторы от врождённого источника', () => {
    let build = makeBuild();
    build = buildService.setAbilityLevel(build, 'r-strength-innate', 1, rules);
    build = buildService.setAbilityLevel(build, 'r-strength-training', 1, rules);

    const version = service.toVersion(build, rules, config);
    const weight = version.characteristics.find((c) => c.ruleId === 'r-weight');

    // Сила {3|0} + врождённый +1 → Вес {4|0}; «Тренировка» (+3) в Вес не входит.
    expect(weight?.base).toEqual({ base: 4, size: 0 });
    const strength = version.characteristics.find((c) => c.ruleId === 'r-strength');
    expect(strength?.modifiers.reduce((sum, m) => sum + m.delta, 0)).toBe(4);
  });
});

describe('Round-trip версии (create → edit → save)', () => {
  it('поля и авто-ресурс ОД стабильны после fromVersion→toVersion', () => {
    const original = service.toVersion(makeBuild(), rules, config);
    const restored = buildService.fromVersion(original, 1, rules);
    const again = service.toVersion(restored, rules, config);

    expect(again.name).toBe(original.name);
    expect(again.raceRuleId).toBe(original.raceRuleId);
    expect(again.points).toEqual(original.points);
    expect(again.ageYears).toBeNull();
    expect(again.inventory).toEqual(original.inventory);
    expect(odOf(again)).toEqual(odOf(original));
  });

  it('orTotal без лимита (null) сохраняется и переживает round-trip', () => {
    const noOrLimit: CharacterCreationConfig = { osTotal: null, orTotal: null, moneyBudget: null };
    const build = makeBuild({ abilities: [{ ruleId: 'r-costly', level: 1 }] });
    const version = service.toVersion(build, rules, noOrLimit);
    expect(version.points.orTotal).toBeNull();

    const restored = buildService.fromVersion(version, 1, rules);
    const again = service.toVersion(restored, rules, noOrLimit);
    expect(again.points.orTotal).toBeNull();
    expect(again.points.orSpent).toBe(2);
  });

  it('budgets (osTotal/moneyBudget) переносятся в версию', () => {
    const version = service.toVersion(makeBuild(), rules, config);

    expect(version.budgets).toEqual({ osTotal: 10, moneyBudget: 100 });
  });

  it('изменённый характер пересчитывается и в ресурсе ОД, и в характеристиках', () => {
    let build = makeBuild();
    build = buildService.setAbilityLevel(build, 'r-strength-training', 1, rules);
    const version = service.toVersion(build, rules, config);
    const restored = buildService.fromVersion(version, 1, rules);
    const again = service.toVersion(restored, rules, config);

    expect(again.characteristics.find((c) => c.ruleId === 'r-strength')).toEqual(
      version.characteristics.find((c) => c.ruleId === 'r-strength'),
    );
    expect(odOf(again)).toEqual(odOf(version));
  });
});
