import { describe, it, expect } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacterMigrationService } from '@/modules/Roleplay/Character/Service/CharacterMigrationService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { fetchRevision } from '@/modules/Roleplay/Space/Mock/mockSpaces';

const dim = (base: number, size = 0) => ({ base, size });

const base = (id: string, code: string, type: Rule['type'], name: string, spec?: Rule['spec']): Rule => ({
  id,
  code,
  type,
  name,
  description: `Описание ${name}`,
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
  spec,
});

/** Минимальный валидный набор правил: раса + характеристики + способность + предмет. */
function makeRules(): Rule[] {
  return [
    base('rule-strength', 'strength', 'characteristic', 'Сила', { type: 'characteristic', group: 'primary' }),
    base('rule-dexterity', 'dexterity', 'characteristic', 'Ловкость', { type: 'characteristic', group: 'primary' }),
    base('rule-human', 'human', 'race', 'Человек', {
      parent_race_code: null,
      cost_os: 0,
      characteristics: [
        { characteristic_code: 'strength', mode: 'purchased', base: dim(3), purchase: [{ cost: 1, value: dim(4) }] },
        { characteristic_code: 'dexterity', mode: 'fixed', base: dim(4) },
      ],
      abilities: [],
    }),
    base('rule-toughness', 'toughness', 'ability', 'Стойкость', {
      type: 'trait',
      zones: { os: { kind: 'array', levels_cost: [2] } },
      requirements: [
        { level: 1, requirements: [{ type: 'characteristic_value', characteristic_code: 'strength', min: dim(3) }] },
      ],
      grants: [],
      parent_ability_code: null,
    }),
    base('rule-focus', 'focus', 'ability', 'Сосредоточенность', {
      type: 'trait',
      zones: { os: { kind: 'array', levels_cost: [3] } },
      requirements: [{ level: 1, requirements: [{ type: 'has_ability', ability_code: 'toughness', min_level: 1 }] }],
      grants: [],
      parent_ability_code: null,
    }),
    base('rule-sword', 'sword', 'item', 'Меч', {
      category: 'other',
      cost_gm: 10,
      weight: null,
      special_rule_codes: [],
    }),
    base('rule-sense', 'vision', 'sense', 'Зрение', {
      type: 'sense',
      status: 'precise',
      radius: dim(30),
    }),
  ];
}

function makeVersion(senses: CharacterVersion['senses'] = []): CharacterVersion {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'space',
    rulesRevision: 1,
    raceRuleId: 'rule-human',
    characteristics: [
      { ruleId: 'rule-strength', base: dim(3), modifiers: [] },
      { ruleId: 'rule-dexterity', base: dim(4), modifiers: [] },
    ],
    resources: [],
    abilities: [{ ruleId: 'rule-toughness', level: 1 }],
    points: { osSpent: 2, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 25 },
    money: 10,
    ageYears: null,
    inventory: [{ id: 1, ruleId: 'rule-sword', quantity: 1, equipped: false }],
    states: [],
    senses,
    budgets: { osTotal: 10, moneyBudget: 100 },
  };
}

const service = new CharacterMigrationService();

function migrate(version: CharacterVersion, oldRules: Rule[], newRules: Rule[]) {
  return service.migrate({
    version,
    oldRules,
    oldSpaceId: 1,
    newRules,
    newSpaceId: 2,
    newSpaceCode: 'new-space',
    newRevision: 2,
    effectiveLimits: {
      osTotal: version.budgets?.osTotal ?? null,
      orTotal: version.points.orTotal,
      moneyBudget: version.budgets?.moneyBudget ?? null,
    },
  });
}

describe('CharacterMigrationService', () => {
  it('добавляет поля статуса и дальности старому экземпляру чувства', () => {
    const legacySense = { ruleId: 'rule-sense', value: 0, modifiers: [] } as unknown as CharacterSenseValue;
    const result = migrate(makeVersion([legacySense]), makeRules(), makeRules());

    expect(result.version.senses[0]).toMatchObject({
      status: 'precise',
      radius: { base: 30, size: 0 },
    });
  });

  it('чистый ремап по code: id правила сменился, версия ссылается на новый id (kind ok)', () => {
    const oldRules = makeRules();
    const newRules = makeRules().map((rule) =>
      rule.id === 'rule-toughness' ? { ...rule, id: 'rule-toughness-v2' } : rule,
    );
    const result = migrate(makeVersion(), oldRules, newRules);
    expect(result.kind).toBe('ok');
    expect(result.version.rulesRevision).toBe(2);
    expect(result.version.spaceCode).toBe('new-space');
    expect(result.version.abilities[0].ruleId).toBe('rule-toughness-v2');
    expect(result.problems).toHaveLength(0);
  });

  it('удалённое правило способности → конфликт, способность сброшена', () => {
    const oldRules = makeRules();
    const newRules = makeRules().filter((rule) => rule.id !== 'rule-toughness');
    const result = migrate(makeVersion(), oldRules, newRules);
    expect(result.kind).toBe('conflicts');
    expect(result.problems.some((problem) => problem.kind === 'removedRule')).toBe(true);
    expect(result.version.abilities).toHaveLength(0);
  });

  it('удалённая раса → конфликт, раса сброшена', () => {
    const oldRules = makeRules();
    const newRules = makeRules().filter((rule) => rule.id !== 'rule-human');
    const result = migrate(makeVersion(), oldRules, newRules);
    expect(result.kind).toBe('conflicts');
    expect(result.problems.some((problem) => problem.kind === 'raceRemoved')).toBe(true);
    expect(result.version.raceRuleId).toBeNull();
  });

  it('предмет с удалённым правилом → кастомный «предмет мастера» (не конфликт)', () => {
    const oldRules = makeRules();
    const newRules = makeRules().filter((rule) => rule.id !== 'rule-sword');
    const result = migrate(makeVersion(), oldRules, newRules);
    expect(result.kind).not.toBe('conflicts');
    expect(result.convertedItems).toBe(1);
    const item = result.version.inventory[0];
    expect(item.ruleId).toBeNull();
    expect(item.name).toBe('Меч');
    expect(item.quantity).toBe(1);
  });

  it('удорожание способности → перерасход реального лимита (конфликт + красный diff)', () => {
    const oldRules = makeRules();
    const newRules = makeRules().map((rule) =>
      rule.id === 'rule-toughness'
        ? base('rule-toughness', 'toughness', 'ability', 'Стойкость', {
            type: 'trait',
            zones: { os: { kind: 'array', levels_cost: [20] } },
            requirements: [],
            grants: [],
            parent_ability_code: null,
          })
        : rule,
    );
    const result = migrate(makeVersion(), oldRules, newRules);
    expect(result.kind).toBe('conflicts');
    expect(result.problems.some((problem) => problem.kind === 'budgetOverrun' && problem.label === 'ОС')).toBe(true);
    expect(result.diffs.some((diff) => diff.label === 'ОС' && diff.tone === 'red')).toBe(true);
  });

  it('невыполненные требования способности → способность сброшена (конфликт)', () => {
    const oldRules = makeRules();
    const newRules = makeRules().map((rule) =>
      rule.id === 'rule-toughness'
        ? base('rule-toughness', 'toughness', 'ability', 'Стойкость', {
            type: 'trait',
            zones: { os: { kind: 'array', levels_cost: [2] } },
            requirements: [
              {
                level: 1,
                requirements: [{ type: 'characteristic_value', characteristic_code: 'strength', min: dim(6) }],
              },
            ],
            grants: [],
            parent_ability_code: null,
          })
        : rule,
    );
    const result = migrate(makeVersion(), oldRules, newRules);
    expect(result.kind).toBe('conflicts');
    expect(result.problems.some((problem) => problem.kind === 'unmetRequirement')).toBe(true);
    expect(result.version.abilities).toHaveLength(0);
  });

  it('изменение значения характеристики → resolved с diff (нейтрально)', () => {
    const oldRules = makeRules();
    const newRules = makeRules().map((rule) =>
      rule.id === 'rule-human'
        ? base('rule-human', 'human', 'race', 'Человек', {
            parent_race_code: null,
            cost_os: 0,
            characteristics: [
              {
                characteristic_code: 'strength',
                mode: 'purchased',
                base: dim(4),
                purchase: [{ cost: 1, value: dim(5) }],
              },
              { characteristic_code: 'dexterity', mode: 'fixed', base: dim(4) },
            ],
            abilities: [],
          })
        : rule,
    );
    const result = migrate(makeVersion(), oldRules, newRules);
    expect(result.kind).toBe('resolved');
    expect(result.diffs.some((diff) => diff.label === 'Сила')).toBe(true);
  });

  it('label проблемы удалённого правила — имя правила (не ruleId)', () => {
    const oldRules = makeRules();
    const newRules = makeRules().filter((rule) => rule.id !== 'rule-toughness');
    const result = migrate(makeVersion(), oldRules, newRules);
    const problem = result.problems.find((entry) => entry.kind === 'removedRule');
    expect(problem?.label).toBe('Стойкость');
  });

  it('характеристика не показывает ложный диф (сохранённое vs вычисленное)', () => {
    // Сохранённая база Ловкости {3}, но раса даёт fixed 4 — модель (и старая, и новая) даёт 4.
    // Честный диф сравнивает значения обеих моделей — ложного «3 → 4» нет.
    const version = makeVersion();
    version.characteristics = [
      { ruleId: 'rule-strength', base: dim(3), modifiers: [] },
      { ruleId: 'rule-dexterity', base: dim(3), modifiers: [] },
    ];
    const result = migrate(version, makeRules(), makeRules());
    expect(result.diffs.some((diff) => diff.label === 'Ловкость')).toBe(false);
  });

  it('каскад: удаление правила роняет зависимую способность (обе в abilities)', () => {
    const version = makeVersion();
    version.abilities = [
      { ruleId: 'rule-toughness', level: 1 },
      { ruleId: 'rule-focus', level: 1 },
    ];
    const oldRules = makeRules();
    const newRules = makeRules().filter((rule) => rule.id !== 'rule-toughness');
    const result = migrate(version, oldRules, newRules);
    const removed = result.abilities.filter((change) => change.kind === 'removed');
    expect(removed.map((change) => change.label)).toContain('Стойкость');
    expect(removed.map((change) => change.label)).toContain('Сосредоточенность');
    expect(result.version.abilities).toHaveLength(0);
  });

  it('бюджетный сдвиг объясняется способностями (пересчитанная стоимость)', () => {
    const oldRules = makeRules();
    const newRules = makeRules().map((rule) =>
      rule.id === 'rule-toughness'
        ? base('rule-toughness', 'toughness', 'ability', 'Стойкость', {
            type: 'trait',
            zones: { os: { kind: 'array', levels_cost: [20] } },
            requirements: [],
            grants: [],
            parent_ability_code: null,
          })
        : rule,
    );
    const result = migrate(makeVersion(), oldRules, newRules);
    const osDiff = result.diffs.find((diff) => diff.label === 'ОС');
    expect(osDiff).toBeDefined();
    expect(osDiff?.explanation).toContain('Стойкость');
  });

  it('потолок на основе отсутствующей характеристики не ужимает характеристику в отрицательное', () => {
    const rules: Rule[] = [
      base('rule-str', 'strength', 'characteristic', 'Сила', { type: 'characteristic', group: 'primary' }),
      base('rule-react', 'reaction', 'characteristic', 'Реакция', {
        type: 'characteristic',
        group: 'base',
        automatic: true,
      }),
      base('rule-shield', 'shield', 'item', 'Щит', {
        category: 'equipment',
        cost_gm: 100,
        weight: null,
        special_rule_codes: [],
        shield: {
          min_strength: { base: 3, size: 1 },
          durability: { base: 4, size: 0 },
          block: { efficiency: { base: 3, size: 0 }, defense: { base: 4, size: 0 }, resistances: [] },
          characteristic_limits: [
            {
              characteristic_code: 'reaction',
              limit: { type: 'characteristic', characteristic_code: 'strength', modifier: -3 },
            },
          ],
        },
      }),
    ];
    const version = {
      ...makeVersion(),
      // Расы нет → Сила не выводится моделью; сохранённая база Силы не участвует в модели.
      characteristics: [{ ruleId: 'rule-str', base: dim(3), modifiers: [] }],
      inventory: [{ id: 1, ruleId: 'rule-shield', quantity: 1, equipped: true }],
    };
    const model = characterEditorService.build(characterBuildService.fromVersion(version, 1, rules), rules, {
      osTotal: null,
      orTotal: null,
      moneyBudget: null,
    });
    const reaction = model.characteristics.find((characteristic) => characteristic.code === 'reaction');
    expect(reaction).toBeDefined();
    expect(reaction?.value.base ?? 0).toBeGreaterThanOrEqual(3);
  });

  it('Гаррик (Ацелатль, rev6 → rev12): раса валидна, характеристики не теряются, «Ночное зрение» — конфликт', async () => {
    const version = versions[3];
    expect(version.raceRuleId).toBe('rule-122');
    const oldRules = (await fetchRevision(2, 6)).rules;
    const newRules = (await fetchRevision(2, 12)).rules;
    const result = service.migrate({
      version,
      oldRules,
      oldSpaceId: 2,
      newRules,
      newSpaceId: 2,
      newSpaceCode: 'actual',
      newRevision: 12,
      effectiveLimits: {
        osTotal: version.budgets?.osTotal ?? null,
        orTotal: version.points.orTotal,
        moneyBudget: version.budgets?.moneyBudget ?? null,
      },
    });
    expect(result.kind).toBe('conflicts');
    expect(result.problems.some((problem) => problem.kind === 'removedRule' && problem.label === 'Ночное зрение')).toBe(
      true,
    );
    expect(result.problems.some((problem) => problem.kind === 'lostCharacteristic')).toBe(false);
    expect(result.problems.some((problem) => problem.kind === 'raceBroken')).toBe(false);
    expect(result.version.raceRuleId).toBe('rule-122');
  });

  it('раса без базовых характеристик: характеристики «исчезнут», раса сброшена', () => {
    const rules: Rule[] = [
      base('rule-str', 'strength', 'characteristic', 'Сила', { type: 'characteristic', group: 'primary' }),
      base('rule-empty-race', 'empty-race', 'race', 'Пустая раса', {
        parent_race_code: null,
        cost_os: 0,
        characteristics: [],
        abilities: [],
      }),
    ];
    const version = {
      ...makeVersion(),
      raceRuleId: 'rule-empty-race',
      characteristics: [{ ruleId: 'rule-str', base: dim(3), modifiers: [] }],
    };
    const result = migrate(version, rules, rules);
    expect(result.problems.some((problem) => problem.kind === 'lostCharacteristic' && problem.label === 'Сила')).toBe(
      true,
    );
    expect(result.problems.some((problem) => problem.kind === 'raceBroken')).toBe(true);
    expect(result.version.raceRuleId).toBeNull();
  });

  it('compareCurrent: добавленная способность и изменение характеристики видны в сравнении', () => {
    const oldRules = makeRules();
    const newRules = [
      ...makeRules(),
      base('rule-might', 'might', 'ability', 'Мощь', {
        type: 'trait',
        zones: { os: { kind: 'array', levels_cost: [3] } },
        requirements: [],
        grants: [
          {
            level: 1,
            grants: [
              {
                type: 'characteristic_modify',
                characteristic_code: 'strength',
                amount: { type: 'fixed', value: 2 },
                source_code: 'training',
              },
            ],
          },
        ],
        parent_ability_code: null,
      }),
    ];
    const original = makeVersion();
    const draft = characterBuildService.fromVersion(original, 1, newRules);
    draft.abilities = [...draft.abilities, { ruleId: 'rule-might', level: 1 }];
    const result = service.compareCurrent(original, draft, oldRules, newRules, {
      osTotal: original.budgets?.osTotal ?? null,
      orTotal: original.points.orTotal,
      moneyBudget: original.budgets?.moneyBudget ?? null,
    });
    expect(result.abilities.some((change) => change.kind === 'added' && change.label === 'Мощь')).toBe(true);
    const strength = result.diffs.find((diff) => diff.label === 'Сила');
    expect(strength).toBeDefined();
    expect(strength?.before).toBe('3');
    expect(strength?.after).toBe('5');
  });

  it('compareCurrent: параметрическая способность — подпись с значением и реальная цена (Врождённая Сила 2 = 4 ОС)', async () => {
    const original = versions[3];
    const oldRules = (await fetchRevision(2, 6)).rules;
    const newRules = (await fetchRevision(2, 12)).rules;
    const draft = characterBuildService.fromVersion(original, 2, newRules);
    const innate = newRules.find((rule) => rule.code === 'innate-strength');
    draft.abilities = [...draft.abilities, { ruleId: innate?.id ?? '', level: 1, parameters: { x: 2 } }];
    const result = service.compareCurrent(original, draft, oldRules, newRules, {
      osTotal: original.budgets?.osTotal ?? null,
      orTotal: original.points.orTotal,
      moneyBudget: original.budgets?.moneyBudget ?? null,
    });
    const added = result.abilities.find((change) => change.kind === 'added');
    expect(added?.label).toBe('Врождённая Сила 2');
    expect(added?.costAfter).toBe(4);
    const osDiff = result.diffs.find((diff) => diff.label === 'ОС');
    expect(osDiff?.explanation).toContain('+4 ОС');
    // Значение характеристики, ограниченное потолком снаряжения (щит: Сила − 3) — с пояснением.
    const dexterity = result.diffs.find((diff) => diff.label === 'Ловкость');
    expect(dexterity?.explanation).toContain('потолок');
  });
});
