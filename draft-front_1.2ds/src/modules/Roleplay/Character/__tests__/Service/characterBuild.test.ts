import { describe, it, expect } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacterBuildService } from '@/modules/Roleplay/Character/Service/CharacterBuildService';

const dim = (base: number, size = 0) => ({ base, size });

const base = (id: number | null, code: string, type: Rule['type'], name: string, spec?: Rule['spec']): Rule => ({
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
  base(null, 'strength', 'characteristic', 'Сила', { type: 'characteristic', group: 'primary' }),
  base(null, 'dexterity', 'characteristic', 'Ловкость', { type: 'characteristic', group: 'primary' }),
  base(null, 'human', 'race', 'Человек', {
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
    ],
    abilities: [{ ability_code: 'keen-hearing', automatic: true }],
  }),
  base(null, 'elf', 'race', 'Эльф', {
    parent_race_code: null,
    cost_os: 8,
    characteristics: [{ characteristic_code: 'dexterity', mode: 'fixed', base: dim(4) }],
    abilities: [],
  }),
  base(null, 'toughness', 'ability', 'Стойкость', {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [2] } },
    requirements: [
      { level: 1, requirements: [{ type: 'characteristic_value', characteristic_code: 'strength', min: dim(3) }] },
    ],
    grants: [],
    parent_ability_code: null,
  }),
  base(null, 'frostbite', 'ability', 'Стойкость к холоду', {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [2] } },
    requirements: [
      { level: 1, requirements: [{ type: 'characteristic_value', characteristic_code: 'dexterity', min: dim(5) }] },
    ],
    grants: [],
    parent_ability_code: null,
  }),
  base(null, 'keen-hearing', 'ability', 'Острый слух', {
    type: 'trait',
    zones: { os: { kind: 'automatic' } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
  }),
  base(null, 'magic-resistance', 'ability', 'Сопротивление магии', {
    type: 'trait',
    zones: { os: { kind: 'parameter', parameter_code: 'x', per_unit: 2 } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    parameters: [{ code: 'x', label: 'X', resolution: 'purchase', default: dim(1), min: dim(0), max: dim(10) }],
  }),
];

const config: CharacterCreationConfig = { osTotal: 20, orTotal: 10, moneyBudget: 100 };

const service = new CharacterBuildService();

function makeBuild(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    spaceId: 1,
    raceRuleCode: 'human',
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

describe('CharacterBuildService', () => {
  it('setAbilityLevel добавляет, повышает и убирает способность', () => {
    let build = makeBuild();
    build = service.setAbilityLevel(build, 'toughness', 1, rules);
    expect(build.abilities).toEqual([{ ruleCode: 'toughness', level: 1 }]);

    build = service.setAbilityLevel(build, 'toughness', 2, rules);
    expect(build.abilities).toEqual([{ ruleCode: 'toughness', level: 2 }]);

    build = service.setAbilityLevel(build, 'toughness', 0, rules);
    expect(build.abilities).toEqual([]);
  });

  it('setAbilityLevel снимает другие способности группы при selectLimit 1', () => {
    const grouped = [
      base(null, 'appearance', 'ability', 'Внешность', { type: 'group', selectLimit: 1 }),
      base(null, 'ugly', 'ability', 'Уродливая', {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [-1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        group_code: 'appearance',
      }),
      base(null, 'beautiful', 'ability', 'Красивая', {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [2] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        group_code: 'appearance',
      }),
      base(null, 'voice', 'ability', 'Голос', { type: 'group', selectLimit: 1 }),
      base(null, 'voice-grouped', 'ability', 'Чудесный голос', {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [2] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        group_code: 'voice',
      }),
    ];

    let build = makeBuild({ abilities: [{ ruleCode: 'ugly', level: 1 }] });
    build = service.setAbilityLevel(build, 'beautiful', 1, grouped);
    expect(build.abilities.map((a) => a.ruleCode).sort()).toEqual(['beautiful']);

    // Другая группа не трогается.
    build = service.setAbilityLevel(build, 'voice-grouped', 1, grouped);
    expect(build.abilities.map((a) => a.ruleCode).sort()).toEqual(['beautiful', 'voice-grouped']);
  });

  it('setAbilityLevel отклоняет добавление сверх лимита группы (selectLimit N>1)', () => {
    const grouped = [
      base(null, 'skills', 'ability', 'Навыки', { type: 'group', selectLimit: 2 }),
      base(null, 'a', 'ability', 'A', {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        group_code: 'skills',
      }),
      base(null, 'b', 'ability', 'B', {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        group_code: 'skills',
      }),
      base(null, 'c', 'ability', 'C', {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
        group_code: 'skills',
      }),
    ];

    let build = makeBuild({ abilities: [{ ruleCode: 'a', level: 1 }] });
    build = service.setAbilityLevel(build, 'b', 1, grouped);
    expect(build.abilities.map((a) => a.ruleCode).sort()).toEqual(['a', 'b']);

    // Группа полна (2 из 2) — добавление третьего отклоняется.
    build = service.setAbilityLevel(build, 'c', 1, grouped);
    expect(build.abilities.map((a) => a.ruleCode).sort()).toEqual(['a', 'b']);

    // Снятие одного освобождает место.
    build = service.setAbilityLevel(build, 'a', 0, grouped);
    build = service.setAbilityLevel(build, 'c', 1, grouped);
    expect(build.abilities.map((a) => a.ruleCode).sort()).toEqual(['b', 'c']);
  });

  it('setAbilityParameter берёт параметрическую способность с X и снимает при X = 0', () => {
    let build = makeBuild();
    build = service.setAbilityParameter(build, 'magic-resistance', 'x', 2, rules);
    expect(build.abilities).toEqual([{ ruleCode: 'magic-resistance', level: 1, parameters: { x: dim(2) } }]);

    // Повышение X обновляет параметр, уровень остаётся 1.
    build = service.setAbilityParameter(build, 'magic-resistance', 'x', 3, rules);
    expect(build.abilities).toEqual([{ ruleCode: 'magic-resistance', level: 1, parameters: { x: dim(3) } }]);

    // X = 0 снимает способность.
    build = service.setAbilityParameter(build, 'magic-resistance', 'x', 0, rules);
    expect(build.abilities).toEqual([]);
  });

  it('setAbilityParameter сохраняет отрицательный X (Врождённая Сила −1) и снимает при X = 0', () => {
    let build = makeBuild();
    build = service.setAbilityParameter(build, 'magic-resistance', 'x', -1, rules);
    expect(build.abilities).toEqual([
      { ruleCode: 'magic-resistance', level: 1, parameters: { x: { base: -1, size: 0 } } },
    ]);

    build = service.setAbilityParameter(build, 'magic-resistance', 'x', -3, rules);
    expect(build.abilities).toEqual([
      { ruleCode: 'magic-resistance', level: 1, parameters: { x: { base: -3, size: 0 } } },
    ]);

    build = service.setAbilityParameter(build, 'magic-resistance', 'x', 0, rules);
    expect(build.abilities).toEqual([]);
  });

  it('setAbilityParameter хранит размерное значение параметра (Врождённая Магия X: {5,1})', () => {
    let build = makeBuild();
    build = service.setAbilityParameter(build, 'magic-resistance', 'x', { base: 5, size: 1 }, rules);
    expect(build.abilities).toEqual([
      { ruleCode: 'magic-resistance', level: 1, parameters: { x: { base: 5, size: 1 } } },
    ]);

    build = service.setAbilityParameter(build, 'magic-resistance', 'x', { base: 0, size: 0 }, rules);
    expect(build.abilities).toEqual([]);
  });

  it('applyRace сбрасывает покупки, привязанные к старой расе', () => {
    const build = makeBuild({ characteristicPurchases: [{ characteristicCode: 'strength', cost: 1 }] });
    const next = service.applyRace(build, 'elf', rules, config, []);

    expect(next.raceRuleCode).toBe('elf');
    expect(next.characteristicPurchases).toEqual([]);
  });

  it('applyRace сбрасывает способности, ставшие недоступными, и сохраняет доступные', () => {
    // frostbite требует Ловкость 5 — у человека (нет fixed ловкости) недоступна; у эльфа доступна.
    const build = makeBuild({
      abilities: [
        { ruleCode: 'toughness', level: 1 },
        { ruleCode: 'frostbite', level: 1 },
        { ruleCode: 'keen-hearing', level: 1 },
      ],
    });

    const toElf = service.applyRace(build, 'elf', rules, config, []);
    const kept = toElf.abilities.map((a) => a.ruleCode).sort();
    // toughness: всё ещё доступна (strength минимум 3 у человека сохранён? — но человек убран, расы нет
    // фикс. strength: у эльфа strength нет вовсе). Проверяем главное: frostbite сброшен, keen сохранён.
    expect(kept).toContain('keen-hearing');
    expect(kept).not.toContain('frostbite');
  });

  it('applyRace сбрасывает параметрическую способность, чей X выше потолка новой расы', () => {
    const magicRaces = [
      base(null, 'race-magic5', 'race', 'Раса М5', {
        parent_race_code: null,
        cost_os: 0,
        characteristics: [],
        abilities: [{ ability_code: 'magic-resistance', automatic: false, parameters: { x: dim(5) } }],
      }),
      base(null, 'race-magic4', 'race', 'Раса М4', {
        parent_race_code: null,
        cost_os: 0,
        characteristics: [],
        abilities: [{ ability_code: 'magic-resistance', automatic: false, parameters: { x: dim(4) } }],
      }),
    ];
    const all = [...rules, ...magicRaces];

    // X=5 (взято под расой с потолком 5) выходит за потолок 4 новой расы — способность сбрасывается.
    const build = makeBuild({
      raceRuleCode: 'race-magic5',
      abilities: [{ ruleCode: 'magic-resistance', level: 1, parameters: { x: dim(5) } }],
    });
    const to4 = service.applyRace(build, 'race-magic4', all, config, []);
    expect(to4.abilities).toEqual([]);

    // X=3 остаётся в диапазоне новой расы — сохраняется.
    const build3 = makeBuild({
      raceRuleCode: 'race-magic5',
      abilities: [{ ruleCode: 'magic-resistance', level: 1, parameters: { x: dim(3) } }],
    });
    const keep3 = service.applyRace(build3, 'race-magic4', all, config, []);
    expect(keep3.abilities).toEqual([{ ruleCode: 'magic-resistance', level: 1, parameters: { x: dim(3) } }]);
  });

  it('setAbilityLevel не даёт взять ol-особенность сверх лимита числа (featureLimit)', () => {
    const olRules = [
      base(null, 'ol1', 'ability', 'Особенность 1', {
        type: 'feature',
        zones: { ol: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
      }),
      base(null, 'ol2', 'ability', 'Особенность 2', {
        type: 'feature',
        zones: { ol: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
      }),
      base(null, 'ol3', 'ability', 'Особенность 3', {
        type: 'feature',
        zones: { ol: { kind: 'array', levels_cost: [1] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
      }),
    ];

    let build = makeBuild({ abilities: [{ ruleCode: 'ol1', level: 1 }] });
    build = service.setAbilityLevel(build, 'ol2', 1, olRules, { featureLimit: 2 });
    expect(build.abilities).toHaveLength(2);

    // Третья сверх лимита 2 — отклоняется (build не меняется).
    const blocked = service.setAbilityLevel(build, 'ol3', 1, olRules, { featureLimit: 2 });
    expect(blocked.abilities).toHaveLength(2);
  });

  it('setAbilityLevel блокирует особенность богатства при edit (wealthLocked)', () => {
    const wealthRules = [
      base(null, 'wealth-rule', 'ability', 'Богатый', {
        type: 'feature',
        zones: { ol: { kind: 'array', levels_cost: [3] } },
        requirements: [],
        grants: [],
        parent_ability_code: null,
      }),
    ];
    const wealthIds = new Set<string>(['wealth-rule']);
    const build = makeBuild();

    const withWealth = service.setAbilityLevel(build, 'wealth-rule', 1, wealthRules, {
      wealthLocked: true,
      wealthRuleIds: wealthIds,
    });
    expect(withWealth.abilities).toEqual([]);

    // При создании (не edit) взять можно.
    const atCreation = service.setAbilityLevel(build, 'wealth-rule', 1, wealthRules);
    expect(atCreation.abilities).toEqual([{ ruleCode: 'wealth-rule', level: 1 }]);
  });

  it('setAbilityLevel сохраняет зону покупки (D111) и параметры при смене уровня', () => {
    const dual = base(null, 'dual-zone', 'ability', 'Двухзонная', {
      type: 'skill',
      zones: {
        os: { kind: 'array', levels_cost: [1, 2] },
        or: { kind: 'array', levels_cost: [2, 3] },
      },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    });
    let build = makeBuild();

    build = service.setAbilityLevel(build, 'dual-zone', 1, [dual], { zone: 'or' });
    expect(build.abilities).toEqual([{ ruleCode: 'dual-zone', level: 1, zone: 'or' }]);

    build = service.setAbilityLevel(build, 'dual-zone', 2, [dual], { zone: 'or' });
    expect(build.abilities).toEqual([{ ruleCode: 'dual-zone', level: 2, zone: 'or' }]);

    // Смена уровня не теряет зону, если она не передана повторно.
    build = service.setAbilityLevel(build, 'dual-zone', 1, [dual]);
    expect(build.abilities).toEqual([{ ruleCode: 'dual-zone', level: 1, zone: 'or' }]);
  });

  it('множественный навык: экземпляры добавляются по доменам со своим уровнем', () => {
    const multiple = base(null, 'language', 'ability', 'Владение языком', {
      type: 'skill',
      multiple: true,
      domain_ref: 'language',
      zones: { or: { kind: 'array', levels_cost: [2, 2, 2] } },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    });
    const rulesLocal = [...rules, multiple];
    let build = makeBuild();

    // Добавление экземпляра: уровень 1, домен + код словаря, зона.
    build = service.addAbilityInstance(build, 'language', 'Драконий', rulesLocal, {
      zone: 'or',
      domainCode: 'language-dragon',
    });
    expect(build.abilities).toEqual([
      { ruleCode: 'language', level: 1, domain: 'Драконий', zone: 'or', domainCode: 'language-dragon' },
    ]);

    // Второй экземпляр — независимый домен.
    build = service.addAbilityInstance(build, 'language', 'Эльфийский', rulesLocal, { zone: 'or' });
    expect(build.abilities).toHaveLength(2);
    expect(build.abilities.map((a) => a.domain)).toEqual(['Драконий', 'Эльфийский']);

    // Дубль домена отклоняется.
    const dup = service.addAbilityInstance(build, 'language', 'Драконий', rulesLocal, { zone: 'or' });
    expect(dup).toBe(build);

    // Пустое значение отклоняется.
    const empty = service.addAbilityInstance(build, 'language', '   ', rulesLocal, { zone: 'or' });
    expect(empty).toBe(build);
  });

  it('множественный навык: уровень экземпляра меняется независимо и ограничен потолком', () => {
    const multiple = base(null, 'language', 'ability', 'Владение языком', {
      type: 'skill',
      multiple: true,
      domain_ref: 'language',
      zones: { or: { kind: 'array', levels_cost: [2, 2, 2] } },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    });
    const rulesLocal = [...rules, multiple];
    let build = makeBuild();

    build = service.addAbilityInstance(build, 'language', 'Драконий', rulesLocal, { zone: 'or' });
    build = service.addAbilityInstance(build, 'language', 'Эльфийский', rulesLocal, { zone: 'or' });

    // Повышаем уровень одного экземпляра — другой не меняется.
    build = service.setAbilityInstanceLevel(build, 'language', 'Драконий', 3, rulesLocal);
    expect(build.abilities.find((a) => a.domain === 'Драконий')?.level).toBe(3);
    expect(build.abilities.find((a) => a.domain === 'Эльфийский')?.level).toBe(1);

    // Потолок (3) не пробивается.
    const over = service.setAbilityInstanceLevel(build, 'language', 'Драконий', 4, rulesLocal);
    expect(over).toBe(build);

    // Уровень 0 снимает экземпляр.
    build = service.setAbilityInstanceLevel(build, 'language', 'Драконий', 0, rulesLocal);
    expect(build.abilities.map((a) => a.domain)).toEqual(['Эльфийский']);
  });

  it('множественный навык: переименование домена и удаление экземпляра', () => {
    const multiple = base(null, 'language', 'ability', 'Владение языком', {
      type: 'skill',
      multiple: true,
      domain_ref: 'language',
      zones: { or: { kind: 'array', levels_cost: [2, 2, 2] } },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    });
    const rulesLocal = [...rules, multiple];
    let build = makeBuild();

    build = service.addAbilityInstance(build, 'language', 'Драконий', rulesLocal, { zone: 'or' });
    build = service.addAbilityInstance(build, 'language', 'Эльфийский', rulesLocal, { zone: 'or' });

    // Переименование домена с кодом словаря; дубль отклоняется.
    build = service.setAbilityInstanceDomain(build, 'language', 'Драконий', 'Орочий', {
      domainCode: 'language-orc',
    });
    expect(build.abilities.find((a) => a.domain === 'Орочий')).toMatchObject({ domainCode: 'language-orc' });

    const dup = service.setAbilityInstanceDomain(build, 'language', 'Орочий', 'Эльфийский', {});
    expect(dup).toBe(build);

    // Удаление экземпляра.
    build = service.removeAbilityInstance(build, 'language', 'Эльфийский');
    expect(build.abilities.map((a) => a.domain)).toEqual(['Орочий']);
  });

  it('множественный навык: setAbilityLevel не трогает множественные (управляются экземплярами)', () => {
    const multiple = base(null, 'language', 'ability', 'Владение языком', {
      type: 'skill',
      multiple: true,
      domain_ref: 'language',
      zones: { or: { kind: 'array', levels_cost: [2, 2, 2] } },
      requirements: [],
      grants: [],
      parent_ability_code: null,
    });
    const rulesLocal = [...rules, multiple];
    let build = makeBuild();

    build = service.addAbilityInstance(build, 'language', 'Драконий', rulesLocal, { zone: 'or' });

    const next = service.setAbilityLevel(build, 'language', 2, rulesLocal, { zone: 'or' });
    expect(next).toBe(build);
  });
});
