import { describe, it, expect } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacterOverviewService } from '@/modules/Roleplay/Character/Service/Overview/CharacterOverviewService';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';

const base = (id: number | null, code: string, type: Rule['type'], name: string, spec?: Rule['spec']): Rule => ({
  id,
  code,
  type,
  name,
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  createdAt: 1767225600,
  spec,
});

const rules: Rule[] = [
  base(null, 'borba', 'ability', 'Борьба', {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [2, 2, 3] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
  }),
  base(null, 'vladenie-oruzhiem', 'ability', 'Владение оружием', {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [1, 1, 1] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    multiple: true,
    domain_ref: 'weapon-family',
  }),
  base(null, 'mechi', 'weapon_family', 'Мечи'),
  base(null, 'luki', 'weapon_family', 'Луки'),
];

function versionWith(overrides: Partial<CharacterVersion> = {}): CharacterVersion {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: null,
    characteristics: [],
    resources: [],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 0 },
    money: 0,
    ageYears: null,
    inventory: [],
    states: [],
    senses: [],
    ...overrides,
  };
}

const service = new CharacterOverviewService();

describe('CharacterOverviewService: вкладка способностей', () => {
  it('не-multiple навык с дублями уровней — одна строка с максимумом', () => {
    const overview = service.build(
      versionWith({
        abilities: [
          { ruleCode: 'borba', level: 2 },
          { ruleCode: 'borba', level: 1 },
        ],
      }),
      rules,
    );

    const borba = overview.abilities.filter((ability) => ability.ruleCode === 'borba');
    expect(borba).toHaveLength(1);
    expect(borba[0].level).toBe(2);
    expect(borba[0].domainLabel).toBeNull();
  });

  it('множественный навык — отдельные строки с подписью домена', () => {
    const overview = service.build(
      versionWith({
        abilities: [
          { ruleCode: 'vladenie-oruzhiem', level: 1, domain: 'Мечи', domainCode: 'mechi' },
          { ruleCode: 'vladenie-oruzhiem', level: 2, domain: 'Луки', domainCode: 'luki' },
        ],
      }),
      rules,
    );

    const rows = overview.abilities.filter((ability) => ability.ruleCode === 'vladenie-oruzhiem');
    expect(rows).toHaveLength(2);
    expect(rows.map((row) => `${row.domainLabel}:${row.level}`).sort()).toEqual(['Луки:2', 'Мечи:1']);
  });

  it('заклинание несёт мощь, контроль и длительность', () => {
    const overview = service.build(
      versionWith({
        abilities: [{ ruleCode: 'discharge', level: 1 }],
      }),
      [
        ...rules,
        base(null, 'action-points', 'resource', 'Очки Действий'),
        base(null, 'discharge', 'ability', 'Разряд', {
          type: 'spell',
          zones: {},
          requirements: [],
          grants: [],
          parent_ability_code: null,
          action_components: [{ type: 'resource', resource_code: 'action-points', amount: 4, label: 'Сотворение' }],
          hit_resolution: { type: 'attack' },
          parameters: [{ code: 'x', label: 'X', resolution: 'activation', default: { base: 3, size: 0 } }],
          spell: {
            power: { type: 'parameter', parameter_code: 'x' },
            control: { base: 3, size: -1 },
            duration: { type: 'instant' },
          },
        }),
      ],
    );
    const discharge = overview.abilities.find((ability) => ability.ruleCode === 'discharge');
    expect(discharge?.spellPowerLabel).toBe('x↑');
    expect(discharge?.spellControlLabel).toBe('3↓');
    expect(discharge?.spellDurationLabel).toBe('Мгновенное');
    expect(discharge?.spellCastCost).toBe(4);
  });

  it('фикстура Торвина показывает срез магии', () => {
    const overview = service.build(versions[1], ruleCatalog);
    const discharge = overview.abilities.find((ability) => ability.ruleCode === 'discharge');
    expect(discharge?.spellPowerLabel).toBe('x↑');
    expect(discharge?.spellControlLabel).toBe('3↓');
    expect(discharge?.domainLabel).toBe('Арканист');
    expect(overview.abilities.some((ability) => ability.ruleCode === 'becoming-arcanist')).toBe(true);
    expect(overview.inventory.some((item) => item.ruleCode === 'magic-core')).toBe(true);
    expect(overview.characteristics.some((entry) => entry.ruleCode === 'magic-power')).toBe(true);
    expect(overview.characteristics.some((entry) => entry.ruleCode === 'magic')).toBe(false);
  });
});
