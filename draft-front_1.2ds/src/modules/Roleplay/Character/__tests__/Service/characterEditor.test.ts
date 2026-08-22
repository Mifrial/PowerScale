import { describe, it, expect } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';

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

const rules: Rule[] = [
  base('rule-strength', 'strength', 'characteristic', 'Сила', { type: 'characteristic', group: 'primary' }),
  base('rule-dexterity', 'dexterity', 'characteristic', 'Ловкость', { type: 'characteristic', group: 'primary' }),
  base('rule-human', 'human', 'race', 'Человек', {
    parent_race_code: null,
    cost_os: 0,
    characteristics: [
      {
        characteristic_code: 'strength',
        mode: 'purchased',
        base: dim(3),
        purchase: [
          { cost: 1, value: dim(4) },
          { cost: 3, value: dim(5) },
        ],
      },
      { characteristic_code: 'dexterity', mode: 'fixed', base: dim(4) },
    ],
    abilities: [{ ability_code: 'keen-hearing', automatic: true }],
  }),
  base('rule-training', 'training', 'source', 'Тренировка'),
  base('rule-toughness', 'toughness', 'ability', 'Стойкость', {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [2] } },
    requirements: [
      { level: 1, requirements: [{ type: 'characteristic_value', characteristic_code: 'strength', min: dim(3) }] },
    ],
    grants: [
      {
        level: 1,
        grants: [
          {
            type: 'characteristic_modify',
            characteristic_code: 'dexterity',
            amount: { type: 'fixed', value: 1 },
            source_code: 'training',
          },
        ],
      },
    ],
    parent_ability_code: null,
  }),
  base('rule-agility', 'agility', 'ability', 'Ловкость рук', {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [3] } },
    requirements: [],
    grants: [
      {
        level: 1,
        grants: [
          {
            type: 'characteristic_modify',
            characteristic_code: 'dexterity',
            amount: { type: 'fixed', value: 2 },
            source_code: 'training',
          },
        ],
      },
    ],
    parent_ability_code: null,
  }),
  base('rule-perfection', 'perfection', 'source', 'Совершенство'),
  base('rule-precision', 'precision', 'ability', 'Точность', {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [2] } },
    requirements: [],
    grants: [
      {
        level: 1,
        grants: [
          {
            type: 'characteristic_modify',
            characteristic_code: 'dexterity',
            amount: { type: 'fixed', value: 3 },
            source_code: 'perfection',
          },
        ],
      },
    ],
    parent_ability_code: null,
  }),
  base('rule-frostbite', 'frostbite', 'ability', 'Стойкость к холоду', {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [2] } },
    requirements: [
      { level: 1, requirements: [{ type: 'characteristic_value', characteristic_code: 'strength', min: dim(4) }] },
    ],
    grants: [],
    parent_ability_code: null,
  }),
  base('rule-keen', 'keen-hearing', 'ability', 'Острый слух', {
    type: 'trait',
    zones: { os: { kind: 'automatic' } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
  }),
  base('rule-meditation', 'meditation', 'ability', 'Медитация', {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [3, 4] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
  }),
  base('rule-archery', 'archery', 'ability', 'Лучник', {
    type: 'skill',
    zones: { or: { kind: 'progression', max_level: 3, base_cost: 2, step: 1 } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
  }),
  base('rule-dual', 'dual-zone', 'ability', 'Двухзонная', {
    type: 'skill',
    zones: {
      os: { kind: 'array', levels_cost: [1, 2] },
      or: { kind: 'array', levels_cost: [2, 3] },
    },
    requirements: [],
    grants: [],
    parent_ability_code: null,
  }),
  base('rule-magic-res', 'magic-resistance', 'ability', 'Сопротивление магии', {
    type: 'trait',
    zones: { os: { kind: 'parameter', parameter_code: 'x', per_unit: 2 } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    parameters: [{ code: 'x', label: 'X', resolution: 'purchase', default: dim(1) }],
  }),
  base('rule-mobility', 'mobility', 'ability', 'Манёвренность', {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [2, 2] } },
    requirements: [
      { level: 1, requirements: [{ type: 'characteristic_value', characteristic_code: 'strength', min: dim(5) }] },
    ],
    grants: [],
    parent_ability_code: null,
  }),
];

const config: CharacterCreationConfig = { osTotal: 10, orTotal: 20, moneyBudget: 100 };

const service = new CharacterEditorService();

function makeBuild(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    spaceId: 1,
    raceRuleId: null,
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

function ability(ruleId: string, level: number) {
  return { ruleId, level };
}

describe('CharacterEditorService.build', () => {
  it('пустой выбор: расы нет, характеристики пустые, бюджеты нулевые', () => {
    const model = service.build(makeBuild(), rules, config);

    expect(model.race).toEqual({ ruleId: null, name: null, costOs: 0 });
    expect(model.characteristics).toEqual([]);
    expect(model.budgets.os).toEqual({ total: 10, spent: 0, exceeded: false });
  });

  it('раса human: purchased-характеристика на минимуме, fixed — по базе', () => {
    const model = service.build(makeBuild({ raceRuleId: 'rule-human' }), rules, config);

    const strength = model.characteristics.find((c) => c.code === 'strength');
    const dexterity = model.characteristics.find((c) => c.code === 'dexterity');
    expect(strength?.base).toEqual(dim(3));
    expect(dexterity?.base).toEqual(dim(4));
    expect(model.budgets.os.spent).toBe(0);
  });

  it('покупка характеристики за 1 ОС: база из лестницы + трата ОС', () => {
    const model = service.build(
      makeBuild({
        raceRuleId: 'rule-human',
        characteristicPurchases: [{ characteristicCode: 'strength', cost: 1 }],
      }),
      rules,
      config,
    );

    const strength = model.characteristics.find((c) => c.code === 'strength');
    expect(strength?.base).toEqual(dim(4));
    expect(model.budgets.os.spent).toBe(1);
  });

  it('черта за ОС: трата в бюджете, грант даёт модификатор характеристики', () => {
    const model = service.build(
      makeBuild({ raceRuleId: 'rule-human', abilities: [ability('rule-toughness', 1)] }),
      rules,
      config,
    );

    expect(model.budgets.os.spent).toBe(2);
    const dexterity = model.characteristics.find((c) => c.code === 'dexterity');
    expect(dexterity?.delta).toBe(1);
    expect(dexterity?.value).toEqual(dim(5));
    expect(dexterity?.modifiers[0]).toMatchObject({ sourceRuleId: 'rule-training', delta: 1, target: 'dexterity' });
  });

  it('модификаторы одной роли источника не складываются (только макс. плюс)', () => {
    const model = service.build(
      makeBuild({
        raceRuleId: 'rule-human',
        abilities: [ability('rule-toughness', 1), ability('rule-agility', 1)],
      }),
      rules,
      config,
    );

    expect(model.budgets.os.spent).toBe(5);
    const dexterity = model.characteristics.find((c) => c.code === 'dexterity');
    expect(dexterity?.delta).toBe(2);
    expect(dexterity?.value).toEqual(dim(3, 1));
  });

  it('модификаторы от разных источников суммируются (ТР §7: +3 от тренировок + +1 от совершенства = +4)', () => {
    const model = service.build(
      makeBuild({
        raceRuleId: 'rule-human',
        abilities: [ability('rule-toughness', 1), ability('rule-agility', 1), ability('rule-precision', 1)],
      }),
      rules,
      config,
    );

    const dexterity = model.characteristics.find((c) => c.code === 'dexterity');
    // От «Тренировки» (toughness +1, agility +2) — только самый сильный бонус +2;
    // от «Совершенства» (precision +3) — отдельный источник, суммируется: итог +5.
    expect(dexterity?.delta).toBe(5);
    expect(dexterity?.modifiers).toHaveLength(2);
  });

  it('автоматическая расовая способность: automatic и racial', () => {
    const model = service.build(makeBuild({ raceRuleId: 'rule-human' }), rules, config);

    const keen = model.abilities.find((a) => a.code === 'keen-hearing');
    expect(keen?.automatic).toBe(true);
    expect(keen?.racial).toBe(true);
    expect(keen?.zones[0].levelCosts).toEqual([0]);
  });

  it('невыполненное требование: метка причины', () => {
    const model = service.build(makeBuild({ raceRuleId: 'rule-human' }), rules, config);

    const frostbite = model.abilities.find((a) => a.code === 'frostbite');
    expect(frostbite?.levels[0].met).toBe(false);
    expect(frostbite?.levels[0].reason).toContain('Сила');
  });

  it('баг «Манёвренность»: уровень 2 не доступен, пока не выполнен уровень 1', () => {
    const model = service.build(makeBuild({ raceRuleId: 'rule-human' }), rules, config);

    const mobility = model.abilities.find((a) => a.code === 'mobility');
    expect(mobility?.levels).toHaveLength(2);
    // Требование прописано только на level 1 (Сила >= 5, у человека 3).
    expect(mobility?.levels[0].met).toBe(false);
    // Уровень 2 подразумевает владение уровнем 1 — тоже должен быть недоступен.
    expect(mobility?.levels[1].met).toBe(false);
  });

  it('зоны: массив уровней и прогрессия с суммарной тратой', () => {
    const model = service.build(
      makeBuild({ abilities: [ability('rule-meditation', 2), ability('rule-archery', 2)] }),
      rules,
      config,
    );

    expect(model.budgets.or.spent).toBe(12); // 3+4 + (2+3)
    const archery = model.abilities.find((a) => a.code === 'archery');
    expect(archery?.zones[0].levelCosts).toEqual([2, 3, 4]);
    expect(archery?.zones[0].maxLevel).toBe(3);
  });

  it('зона покупки (D111): мультизонная способность списывает только выбранную зону', () => {
    // Куплена за ОР (level 2: or [2,3] = 5 ОР) — ОС не тратятся.
    const inOr = service.build(
      makeBuild({ abilities: [{ ruleId: 'rule-dual', level: 2, zone: 'or' }] }),
      rules,
      config,
    );
    expect(inOr.budgets.or.spent).toBe(5);
    expect(inOr.budgets.os.spent).toBe(0);

    // Куплена за ОС (level 2: os [1,2] = 3 ОС) — ОР не тратятся.
    const inOs = service.build(
      makeBuild({ abilities: [{ ruleId: 'rule-dual', level: 2, zone: 'os' }] }),
      rules,
      config,
    );
    expect(inOs.budgets.os.spent).toBe(3);
    expect(inOs.budgets.or.spent).toBe(0);

    // Без зоны (старый черновик) — инференс первой покупаемой зоны (os), без задвоения.
    const legacy = service.build(makeBuild({ abilities: [{ ruleId: 'rule-dual', level: 2 }] }), rules, config);
    expect(legacy.budgets.os.spent).toBe(3);
    expect(legacy.budgets.or.spent).toBe(0);
  });

  it('превышение лимита отмечается', () => {
    const model = service.build(makeBuild({ abilities: [ability('rule-agility', 1)] }), rules, {
      ...config,
      osTotal: 1,
    });

    expect(model.budgets.os).toEqual({ total: 1, spent: 3, exceeded: true });
  });

  it('параметрическая цена: дефолт и значение из инстанса', () => {
    const withDefault = service.build(makeBuild({ abilities: [ability('rule-magic-res', 1)] }), rules, config);
    expect(withDefault.budgets.os.spent).toBe(2); // 2 × x, x=1 (дефолт)

    const withChosen = service.build(
      makeBuild({
        abilities: [{ ruleId: 'rule-magic-res', level: 1, parameters: { x: dim(3) } }],
      }),
      rules,
      config,
    );
    expect(withChosen.budgets.os.spent).toBe(6); // 2 × 3

    const zone = withChosen.abilities.find((a) => a.code === 'magic-resistance')?.zones[0];
    expect(zone?.levelCosts).toEqual([6]);
  });

  it('механика «Общие»: 3-я и последующие общие черты доплачивают +2 ОС к os.spent', () => {
    const commonKeyword: Keyword = { id: 20, code: 'common', name: 'Общая', active: true };
    const surchargeMechanic: Mechanic = {
      id: 4,
      code: 'purchase_surcharge',
      name: 'Прогрессивная доплата',
      description: '',
      version: '1.0.0',
    };
    const common = (id: string, code: string): Rule => ({
      ...base(id, code, 'ability', code, {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
      }),
      keywordIds: [20],
    });
    const withMechanics: Rule[] = [
      ...rules,
      common('rule-c1', 'common-1'),
      common('rule-c2', 'common-2'),
      common('rule-c3', 'common-3'),
      common('rule-c4', 'common-4'),
      {
        id: 'rule-surcharge',
        code: 'common-traits-surcharge',
        type: 'simple',
        name: 'Общие черты: прогрессивная доплата',
        description: '',
        spaceId: 1,
        keywordIds: [20],
        mechanicId: 4,
        mechanic_payload: {
          type: 'purchase_surcharge',
          filter: { keyword_code: 'common' },
          free_count: 2,
          surcharge: 2,
        },
        createdAt: '2026-01-01T00:00:00Z',
      },
    ];

    const two = service.build(
      makeBuild({ abilities: [ability('rule-c1', 1), ability('rule-c2', 1)] }),
      withMechanics,
      config,
      [commonKeyword],
      [surchargeMechanic],
    );
    expect(two.budgets.os.spent).toBe(2); // 1+1, доплаты нет

    const three = service.build(
      makeBuild({ abilities: [ability('rule-c1', 1), ability('rule-c2', 1), ability('rule-c3', 1)] }),
      withMechanics,
      config,
      [commonKeyword],
      [surchargeMechanic],
    );
    expect(three.budgets.os.spent).toBe(5); // 1+1+1 + 2 (доплата)
    expect(three.budgets.osSurcharge).toEqual({
      total: 2,
      items: [{ abilityCode: 'common-3', amount: 2 }],
    });

    const four = service.build(
      makeBuild({
        abilities: [ability('rule-c1', 1), ability('rule-c2', 1), ability('rule-c3', 1), ability('rule-c4', 1)],
      }),
      withMechanics,
      config,
      [commonKeyword],
      [surchargeMechanic],
    );
    expect(four.budgets.os.spent).toBe(8); // 1+1+1+1 + 4 (две доплаты)
    expect(four.budgets.osSurcharge).toEqual({
      total: 4,
      items: [
        { abilityCode: 'common-3', amount: 2 },
        { abilityCode: 'common-4', amount: 2 },
      ],
    });
  });

  it('автоматические расовые способности применяют дары (уровень 1)', () => {
    const grantRules: Rule[] = [
      base('rule-strength', 'strength', 'characteristic', 'Сила', { type: 'characteristic', group: 'primary' }),
      base('rule-human', 'human', 'race', 'Человек', {
        parent_race_code: null,
        cost_os: 0,
        characteristics: [{ characteristic_code: 'strength', mode: 'fixed', base: dim(3) }],
        abilities: [{ ability_code: 'stone-skin', automatic: true }],
      }),
      base('rule-stone-skin', 'stone-skin', 'ability', 'Каменная кожа', {
        type: 'trait',
        zones: {},
        requirements: [],
        grants: [
          {
            level: 1,
            grants: [
              {
                type: 'characteristic_modify',
                characteristic_code: 'strength',
                amount: { type: 'fixed', value: 2 },
                source_code: 'innate',
              },
            ],
          },
        ],
        parent_ability_code: null,
      }),
    ];

    const model = service.build(makeBuild({ raceRuleId: 'rule-human' }), grantRules, config);
    const strength = model.characteristics.find((c) => c.code === 'strength');
    expect(strength?.base).toEqual(dim(3));
    expect(strength?.delta).toBe(2);
    expect(strength?.value).toEqual(dim(5));
  });
});

describe('CharacterEditorService.toVersion', () => {
  it('выводит версию: очки, характеристики с модификаторами, сохранённые поля', () => {
    const build = makeBuild({
      raceRuleId: 'rule-human',
      abilities: [ability('rule-toughness', 1)],
      inventory: [{ id: 1, ruleId: 'rule-x', quantity: 1, equipped: false }],
      money: 40,
      olTotal: 3,
    });
    const version = service.toVersion(build, rules, config);

    expect(version.raceRuleId).toBe('rule-human');
    expect(version.points).toEqual({ osSpent: 2, olSpent: 0, olTotal: 3, orSpent: 0, orTotal: 20 });
    expect(version.money).toBe(40);
    expect(version.ageYears).toBeNull();
    expect(version.inventory).toEqual([{ id: 1, ruleId: 'rule-x', quantity: 1, equipped: false }]);
    const dexterity = version.characteristics.find((c) => c.ruleId === 'rule-dexterity');
    expect(dexterity?.base).toEqual(dim(4));
    expect(dexterity?.modifiers[0]).toMatchObject({ delta: 1 });
  });
});

describe('CharacterEditorService: возраст и деньги (заход C)', () => {
  const ageRules: Rule[] = [
    ...rules,
    base('rule-intellect', 'intellect', 'characteristic', 'Интеллект', {
      type: 'characteristic',
      formula: 'min(memory, reasoning)',
      group: 'primary',
    }),
    base('rule-age', 'age', 'age', 'Возраст', {
      type: 'age',
      ages: [
        { name: 'Молодой', ol: 3, featureLimit: 3, effects: [] },
        {
          name: 'Взрослый',
          ol: 4,
          featureLimit: 3,
          effects: [{ characteristic_code: 'intellect', delta: -1, scope: 'для проверок на усвоение нового' }],
        },
        {
          name: 'Старый',
          ol: 7,
          featureLimit: 4,
          effects: [
            { characteristic_code: 'dexterity', delta: -3 },
            { characteristic_code: 'intellect', delta: -2 },
          ],
        },
      ],
    }),
  ];
  // Раса с таблицей лет: Молодой 18–25, Взрослый 25–35, за 35 — «Старый».
  const humanAge = base('rule-human', 'human', 'race', 'Человек', {
    parent_race_code: null,
    cost_os: 0,
    characteristics: [
      { characteristic_code: 'strength', mode: 'fixed', base: dim(3) },
      { characteristic_code: 'dexterity', mode: 'fixed', base: dim(3) },
      { characteristic_code: 'memory', mode: 'fixed', base: dim(3) },
      { characteristic_code: 'reasoning', mode: 'fixed', base: dim(3) },
    ],
    abilities: [],
    age_years: [
      { age: 'Молодой', ageStart: 18, ageEnd: 25 },
      { age: 'Взрослый', ageStart: 25, ageEnd: 35 },
    ],
  });
  const ageCatalog = ageRules.map((rule) => (rule.id === 'rule-human' ? humanAge : rule));

  it('ступень по годам и таблице расы: ОЛ и лимит особенностей', () => {
    const model = service.build(makeBuild({ raceRuleId: 'rule-human', ageYears: 22 }), ageCatalog, config);

    expect(model.personality.hasAgeRule).toBe(true);
    expect(model.personality.ageName).toBe('Молодой');
    expect(model.personality.ol).toBe(3);
    expect(model.personality.featureLimit).toBe(3);
    expect(model.budgets.ol.total).toBe(3);
  });

  it('за диапазонами лет — ступень «Старый» с эффектами к характеристикам', () => {
    const model = service.build(makeBuild({ raceRuleId: 'rule-human', ageYears: 70 }), ageCatalog, config);

    expect(model.personality.ageName).toBe('Старый');
    expect(model.personality.ol).toBe(7);
    const dexterity = model.characteristics.find((c) => c.code === 'dexterity');
    expect(dexterity?.delta).toBe(-3);
  });

  it('шкала возраста: все ступени правила, за диапазонами — «Старый» от последнего максимума', () => {
    const model = service.build(makeBuild({ raceRuleId: 'rule-human', ageYears: 22 }), ageCatalog, config);

    expect(model.personality.ageScale).toEqual([
      { name: 'Молодой', min: 18, max: 25 },
      { name: 'Взрослый', min: 25, max: 35 },
      { name: 'Старый', min: 35, max: null },
    ]);
  });

  it('дефолт возраста: минимум средней ступени шкалы; без расы (нет таблицы лет) — null', () => {
    const withRace = service.build(makeBuild({ raceRuleId: 'rule-human' }), ageCatalog, config);
    expect(withRace.personality.defaultAgeYears).toBe(25);

    const withoutRace = service.build(makeBuild(), ageCatalog, config);
    expect(withoutRace.personality.defaultAgeYears).toBeNull();
  });

  it('условный эффект (scope) в значение не входит, помечается в модификаторах', () => {
    const model = service.build(makeBuild({ raceRuleId: 'rule-human', ageYears: 30 }), ageCatalog, config);

    expect(model.personality.ageName).toBe('Взрослый');
    // Интеллект — производная: условный эффект применяется к её базам (memory/reasoning), но в значение не входит.
    const memory = model.characteristics.find((c) => c.code === 'memory');
    expect(memory?.delta).toBe(0);
    const scoped = memory?.modifiers.find((modifier) => modifier.scope !== null);
    expect(scoped).toMatchObject({ delta: -1, scope: 'для проверок на усвоение нового' });
  });

  it('особенность богатства: эффективный бюджет денег = max(фикс, % от лимита)', () => {
    const wealthKeyword: Keyword = { id: 50, code: 'wealth', name: 'Богатство', active: true };
    const richRule: Rule = {
      id: 'rule-rich',
      code: 'rich',
      type: 'ability',
      name: 'Богатый',
      description: '',
      spaceId: 1,
      keywordIds: [50],
      mechanicId: null,
      createdAt: '2026-01-01T00:00:00Z',
      spec: {
        type: 'feature',
        zones: { ol: { kind: 'array', levels_cost: [3] } },
        requirements: [],
        grants: [{ level: 1, grants: [{ type: 'money', fixed: 400, percent: 400, apply: 'max' }] }],
        parent_ability_code: null,
      },
    };
    const grantRules = [...ageCatalog, richRule];
    const model = service.build(makeBuild({ ageYears: 22, abilities: [ability('rule-rich', 1)] }), grantRules, config, [
      wealthKeyword,
    ]);

    expect(model.budgets.money.total).toBe(400);
    expect(model.personality.wealthRuleIds).toContain('rule-rich');
  });

  it('Нищий: бюджет денег = min(фикс, % от лимита)', () => {
    const grantRules = [
      ...ageCatalog,
      base('rule-pauper', 'pauper', 'ability', 'Нищий', {
        type: 'feature',
        zones: { ol: { kind: 'array', levels_cost: [-1] } },
        requirements: [],
        grants: [{ level: 1, grants: [{ type: 'money', fixed: 10, percent: 10, apply: 'min' }] }],
        parent_ability_code: null,
      }),
    ];
    const model = service.build(
      makeBuild({ ageYears: 22, abilities: [ability('rule-pauper', 1)] }),
      grantRules,
      config,
    );

    expect(model.budgets.money.total).toBe(10);
    expect(model.budgets.ol.spent).toBe(-1);
  });

  it('toVersion: переносит возраст и ОЛ из ступени', () => {
    const build = makeBuild({ raceRuleId: 'rule-human', ageYears: 22, olTotal: 0 });
    const version = service.toVersion(build, ageCatalog, config);

    expect(version.ageYears).toBe(22);
    expect(version.points.olTotal).toBe(3);
  });

  it('перетрата ОЛ (взято больше, чем даёт ступень) отмечается exceeded', () => {
    // Ступень «Молодой» даёт 3 ОЛ; две положительные особенности по 2 ОЛ — перетрата.
    const traitA = base('rule-a', 'feature-a', 'ability', 'Особенность A', {
      type: 'feature',
      zones: { ol: { kind: 'array', levels_cost: [2] } },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    });
    const traitB = base('rule-b', 'feature-b', 'ability', 'Особенность B', {
      type: 'feature',
      zones: { ol: { kind: 'array', levels_cost: [2] } },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    });
    const grantRules = [...ageCatalog, traitA, traitB];
    const model = service.build(
      makeBuild({ raceRuleId: 'rule-human', ageYears: 22, abilities: [ability('rule-a', 1), ability('rule-b', 1)] }),
      grantRules,
      config,
    );

    expect(model.budgets.ol.total).toBe(3);
    expect(model.budgets.ol.spent).toBe(4);
    expect(model.budgets.ol.exceeded).toBe(true);
  });

  it('orTotal без лимита: null сохраняется в версии (edit не блокируется лимитом 0)', () => {
    const noLimit: CharacterCreationConfig = { osTotal: null, orTotal: null, moneyBudget: null };
    const build = makeBuild({ abilities: [ability('rule-meditation', 1)] });
    const version = service.toVersion(build, rules, noLimit);

    expect(version.points.orTotal).toBeNull();
    expect(version.points.orSpent).toBe(3);
  });
});
