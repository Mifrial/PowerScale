import { describe, it, expect } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';
import { CharacterOverviewService } from '@/modules/Roleplay/Character/Service/Overview/CharacterOverviewService';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { racialInnateGearService } from '@/modules/Roleplay/Character/Service/Instance/racialInnateGearService';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';

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

const innateWeapon = (id: string, code: string, name: string, family: string): Rule =>
  base(id, code, 'item', name, {
    category: 'equipment',
    cost_gm: null,
    weight: null,
    innate: true,
    special_rule_codes: [],
    proficiency_family_code: family,
    weapon: {
      min_strength: dim(3),
      durability: dim(5, 2),
      block_profile: { efficiency: dim(5), defense: dim(1), resistances: [] },
      weapon_profiles: [
        {
          type: 'strike',
          distance: { type: 'dimensional', base: 0, size: 0 },
          range: null,
          damage: { formula: { type: 'fixed', value: 1 }, damage_type_code: 'blunt' },
          penetration: { type: 'fixed', value: 0 },
          accuracy: dim(5),
        },
      ],
    },
  } satisfies ItemSpec);

const fixtureRules: Rule[] = [
  innateWeapon('item-ruka', 'ruka', 'Рука', 'fam-hand'),
  innateWeapon('item-noga', 'noga', 'Нога', 'fam-foot'),
  innateWeapon('item-claw', 'claw', 'Коготь', 'fam-claw'),
  base('fam-hand', 'fam-hand', 'weapon_family', 'Рука', { costs: [1, 2, 4] }),
  base('fam-foot', 'fam-foot', 'weapon_family', 'Нога', { costs: [1, 2, 4] }),
  base('fam-claw', 'fam-claw', 'weapon_family', 'Коготь', { costs: [1, 2, 4] }),
  base('rule-prof', 'vladenie-oruzhiem', 'ability', 'Владение оружием', {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [1, 1, 1] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    multiple: true,
    domain_ref: 'weapon-family',
  }),
  base('rule-body', 'humanoid-body', 'ability', 'Гуманоидное тело', {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [0] } },
    requirements: [],
    grants: [
      {
        level: 1,
        grants: [
          { type: 'item', item_code: 'ruka', quantity: 2 },
          { type: 'item', item_code: 'noga', quantity: 2 },
        ],
      },
    ],
    parent_ability_code: null,
  }),
  base('rule-four-arms', 'four-arms', 'ability', 'Четыре руки', {
    type: 'trait',
    zones: { os: { kind: 'array', levels_cost: [0] } },
    requirements: [],
    grants: [{ level: 1, grants: [{ type: 'item', item_code: 'ruka', quantity: 4 }] }],
    parent_ability_code: null,
  }),
  base('rule-species', 'human', 'species', 'Человек', {
    parent_race_code: null,
    abilities: [{ ability_code: 'humanoid-body', automatic: true }],
  }),
  base('rule-race', 'alierets', 'race', 'Алиерц', {
    parent_race_code: 'human',
    cost_os: 10,
    characteristics: [],
    abilities: [],
  }),
  base('rule-four', 'fourhanded', 'race', 'Четырёхрукий', {
    parent_race_code: 'human',
    cost_os: 0,
    characteristics: [],
    abilities: [{ ability_code: 'four-arms', automatic: true }],
  }),
];

function sheet(
  overrides: Partial<{
    raceRuleId: string | null;
    inventory: CharacterBuild['inventory'];
    abilities: CharacterBuild['abilities'];
  }> = {},
) {
  return {
    raceRuleId: 'rule-race' as string | null,
    inventory: [] as CharacterBuild['inventory'],
    abilities: [] as CharacterBuild['abilities'],
    ...overrides,
  };
}

describe('applyRacialInnateGear', () => {
  it('гуманоидная раса: рука×2, нога×2, экип, gifted владение ур.1', () => {
    const next = racialInnateGearService.applyRacialInnateGear(sheet(), fixtureRules);
    const ruka = next.inventory.find((item) => item.ruleId === 'item-ruka');
    const noga = next.inventory.find((item) => item.ruleId === 'item-noga');

    expect(ruka).toMatchObject({ quantity: 2, equipped: true });
    expect(noga).toMatchObject({ quantity: 2, equipped: true });
    expect(next.abilities.filter((ability) => ability.ruleId === 'rule-prof')).toEqual([
      expect.objectContaining({
        level: 1,
        domainCode: 'fam-hand',
        gifted: true,
        zone: 'or',
      }),
      expect.objectContaining({
        level: 1,
        domainCode: 'fam-foot',
        gifted: true,
        zone: 'or',
      }),
    ]);
  });

  it('вид без расы тоже даёт тело', () => {
    const next = racialInnateGearService.applyRacialInnateGear(sheet({ raceRuleId: 'rule-species' }), fixtureRules);

    expect(next.inventory.map((item) => item.ruleId).sort()).toEqual(['item-noga', 'item-ruka']);
  });

  it('без расы — без естественного оружия', () => {
    const next = racialInnateGearService.applyRacialInnateGear(sheet({ raceRuleId: null }), fixtureRules);

    expect(next.inventory).toEqual([]);
    expect(next.abilities).toEqual([]);
  });

  it('раса перекрывает quantity того же item_code', () => {
    const next = racialInnateGearService.applyRacialInnateGear(sheet({ raceRuleId: 'rule-four' }), fixtureRules);
    const ruka = next.inventory.find((item) => item.ruleId === 'item-ruka');

    expect(ruka?.quantity).toBe(4);
    expect(next.inventory.find((item) => item.ruleId === 'item-noga')?.quantity).toBe(2);
  });

  it('мутационный innate не из расового гранта не снимается', () => {
    const next = racialInnateGearService.applyRacialInnateGear(
      sheet({
        inventory: [{ id: 9, ruleId: 'item-claw', quantity: 1, equipped: true, modifierRuleIds: [] }],
      }),
      fixtureRules,
    );

    expect(next.inventory.find((item) => item.ruleId === 'item-claw')).toMatchObject({ id: 9, quantity: 1 });
    expect(next.abilities.some((ability) => ability.domainCode === 'fam-claw')).toBe(false);
  });

  it('уже купленное владение семьи становится gifted (первый уровень бесплатный)', () => {
    const next = racialInnateGearService.applyRacialInnateGear(
      sheet({
        abilities: [{ ruleId: 'rule-prof', level: 2, domain: 'Рука', domainCode: 'fam-hand', zone: 'or' }],
      }),
      fixtureRules,
    );

    expect(next.abilities.find((ability) => ability.domainCode === 'fam-hand')).toMatchObject({
      level: 2,
      gifted: true,
    });
  });

  it('смена расы снимает gifted ур.1 и оставляет купленное владение без gifted', () => {
    const withRace = racialInnateGearService.applyRacialInnateGear(sheet(), fixtureRules);
    const upgraded = {
      ...withRace,
      abilities: withRace.abilities.map((ability) =>
        ability.domainCode === 'fam-hand' ? { ...ability, level: 2 } : ability,
      ),
    };
    const afterDrop = racialInnateGearService.applyRacialInnateGear({ ...upgraded, raceRuleId: null }, fixtureRules);

    expect(afterDrop.inventory).toEqual([]);
    expect(afterDrop.abilities).toEqual([expect.objectContaining({ domainCode: 'fam-hand', level: 2, gifted: false })]);
  });
});

describe('естественное оружие в каталоге и обзоре', () => {
  const ahtar = ruleCatalog.find((rule) => rule.code === 'ahtar');
  const ruka = ruleCatalog.find((rule) => rule.code === 'ruka');
  const noga = ruleCatalog.find((rule) => rule.code === 'noga');
  const body = ruleCatalog.find((rule) => rule.code === 'humanoid-body');
  const strength = ruleCatalog.find((rule) => rule.code === 'strength');
  const editor = new CharacterEditorService();
  const overview = new CharacterOverviewService();
  const config: CharacterCreationConfig = { osTotal: 20, orTotal: 12, moneyBudget: 100 };

  function catalogBuild(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
    return {
      name: 'Тест',
      shortDescription: null,
      fullDescription: null,
      spaceId: 1,
      spaceCode: 'razrabotka',
      rulesRevision: 5,
      raceRuleId: ahtar?.id ?? '',
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

  function versionOf(overrides: Partial<CharacterVersion> = {}): CharacterVersion {
    return {
      name: 'Тест',
      shortDescription: null,
      fullDescription: null,
      spaceCode: 'razrabotka',
      rulesRevision: 5,
      raceRuleId: ahtar?.id ?? null,
      characteristics: strength ? [{ ruleId: strength.id, base: dim(5), modifiers: [] }] : [],
      resources: [],
      abilities: [],
      points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 12 },
      money: 0,
      ageYears: null,
      inventory: [],
      states: [],
      senses: [],
      ...overrides,
    };
  }

  it('humanoid-body, рука и нога есть в ревизии; innate не в закупке', () => {
    expect(body).toBeDefined();
    expect(ruka?.spec).toMatchObject({ innate: true, cost_gm: null });
    expect(noga?.spec).toMatchObject({ innate: true, cost_gm: null });
    expect(
      characterBuildService.buyItem(catalogBuild({ raceRuleId: null }), ruka?.id ?? '', 1, ruleCatalog).inventory,
    ).toEqual([]);
  });

  it('fromVersion гуманоида материализует руки/ноги; gifted владение не списывает ОР', () => {
    expect(ahtar).toBeDefined();
    const build = characterBuildService.fromVersion(versionOf(), 1, ruleCatalog);
    expect(build.inventory.find((item) => item.ruleId === ruka?.id)?.quantity).toBe(2);
    expect(build.inventory.find((item) => item.ruleId === noga?.id)?.quantity).toBe(2);

    const model = editor.build(build, ruleCatalog, config);
    expect(model.budgets.or.spent).toBe(0);

    const hand = build.abilities.find((ability) => ability.domainCode === 'fam-kogti-ruki');
    expect(hand).toMatchObject({ level: 1, gifted: true });
    const upgraded = characterBuildService.setWeaponMastery(
      build,
      hand!.ruleId,
      hand!.domain ?? 'Когти, Руки',
      'fam-kogti-ruki',
      2,
      ruleCatalog,
    );
    expect(editor.build(upgraded, ruleCatalog, config).budgets.or.spent).toBe(2);
    const cannotDrop = characterBuildService.setWeaponMastery(
      upgraded,
      hand!.ruleId,
      hand!.domain ?? 'Когти, Руки',
      'fam-kogti-ruki',
      0,
      ruleCatalog,
    );
    expect(cannotDrop.abilities.find((ability) => ability.domainCode === 'fam-kogti-ruki')).toMatchObject({
      level: 1,
      gifted: true,
    });
  });

  it('обзор без купленного оружия даёт атаки Рука и Нога', () => {
    const card = overview.build(versionOf(), ruleCatalog);
    const names = card.attacks.map((attack) => attack.itemName);

    expect(names).toEqual(expect.arrayContaining(['Рука', 'Нога']));
    expect(names.filter((name) => name === 'Рука')).toHaveLength(1);
    expect(names.filter((name) => name === 'Нога')).toHaveLength(1);
  });
});
