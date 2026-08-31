import { describe, it, expect } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacterOverviewService } from '@/modules/Roleplay/Character/Service/Overview/CharacterOverviewService';

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
});
