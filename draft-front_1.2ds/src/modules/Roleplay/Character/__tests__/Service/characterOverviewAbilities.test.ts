import { describe, it, expect } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacterOverviewService } from '@/modules/Roleplay/Character/Service/Overview/CharacterOverviewService';

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
  base('rule-borba', 'borba', 'ability', 'Борьба', {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [2, 2, 3] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
  }),
  base('rule-prof', 'vladenie-oruzhiem', 'ability', 'Владение оружием', {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [1, 1, 1] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    multiple: true,
    domain_ref: 'weapon-family',
  }),
  base('rule-swords', 'mechi', 'weapon_family', 'Мечи'),
  base('rule-bows', 'luki', 'weapon_family', 'Луки'),
];

function versionWith(overrides: Partial<CharacterVersion> = {}): CharacterVersion {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleId: null,
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
          { ruleId: 'rule-borba', level: 2 },
          { ruleId: 'rule-borba', level: 1 },
        ],
      }),
      rules,
    );

    const borba = overview.abilities.filter((ability) => ability.ruleId === 'rule-borba');
    expect(borba).toHaveLength(1);
    expect(borba[0].level).toBe(2);
    expect(borba[0].domainLabel).toBeNull();
  });

  it('множественный навык — отдельные строки с подписью домена', () => {
    const overview = service.build(
      versionWith({
        abilities: [
          { ruleId: 'rule-prof', level: 1, domain: 'Мечи', domainCode: 'mechi' },
          { ruleId: 'rule-prof', level: 2, domain: 'Луки', domainCode: 'luki' },
        ],
      }),
      rules,
    );

    const rows = overview.abilities.filter((ability) => ability.ruleId === 'rule-prof');
    expect(rows).toHaveLength(2);
    expect(rows.map((row) => `${row.domainLabel}:${row.level}`).sort()).toEqual(['Луки:2', 'Мечи:1']);
  });
});
