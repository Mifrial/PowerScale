import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { MockRuleCatalogMigrationService } from '@/modules/Roleplay/Rule/Mock/MockRuleCatalogMigrationService';
import { mockCombatAbilitySectionByCode } from '@/modules/Roleplay/Rule/Mock/mockCombatAbilitySectionByCode';

function rule(overrides: Partial<Rule>): Rule {
  return {
    id: overrides.id ?? 'rule',
    code: overrides.code ?? 'rule',
    type: overrides.type ?? 'simple',
    name: overrides.name ?? 'Правило',
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

describe('RuleCatalogMigrationService', () => {
  const ruleCatalogMigrationService = new MockRuleCatalogMigrationService();

  it('places species and race rules into the species section', () => {
    const migrated = ruleCatalogMigrationService.migrateRules(
      [
        rule({ id: 'species-elves', code: 'elves', type: 'species', spec: { parent_race_code: null, abilities: [] } }),
        rule({
          id: 'race-wood',
          code: 'wood-elves',
          type: 'race',
          spec: { parent_race_code: 'elves', cost_os: 2, characteristics: [], abilities: [] },
        }),
      ],
      new Map(),
    );

    expect(migrated.map((entry) => entry.catalogSection)).toEqual(['species-elves', 'species-elves']);
  });

  it('uses explicit sections for checks and consumable crystals', () => {
    const migrated = ruleCatalogMigrationService.migrateRules(
      [rule({ type: 'check', code: 'check-initiative' }), rule({ type: 'item', keywordIds: [1, 2] })],
      new Map([
        [1, 'artifact'],
        [2, 'item-section-crystal'],
      ]),
    );

    expect(migrated.map((entry) => entry.catalogSection)).toEqual(['basic-checks', 'items-consumables-magic-crystals']);
  });

  it('distributes combat abilities into speed, accuracy, and power branches', () => {
    const migrated = ruleCatalogMigrationService.migrateRules(
      [
        rule({ code: 'bystryy-udar', type: 'ability' }),
        rule({ code: 'tochnyy-udar', type: 'ability' }),
        rule({ code: 'razmashistyy-udar', type: 'ability' }),
        rule({ code: 'stremitelnyy-udar', type: 'ability' }),
        rule({ code: 'seriya-udarov', type: 'ability' }),
        rule({ code: 'kombinatsiya-udarov', type: 'ability' }),
        rule({ code: 'sovmestnaya-ataka', type: 'ability' }),
        rule({ code: 'medicinskaya-pomoshch', type: 'ability', keywordIds: [9] }),
        rule({
          code: 'kogti_tochnost_v_gibkosti',
          type: 'ability',
          spec: {
            type: 'skill',
            zones: {},
            requirements: [
              { level: 1, requirements: [{ type: 'min_weapon_mastery', keyword_code: '', min_level: 1 }] },
            ],
            grants: [],
            parent_ability_code: null,
          },
        }),
      ],
      new Map([[9, 'medicine']]),
      mockCombatAbilitySectionByCode,
    );

    expect(migrated.map((entry) => entry.catalogSection)).toEqual([
      'abilities-acquired-melee-combat-speed',
      'abilities-acquired-melee-combat-accuracy',
      'abilities-acquired-melee-combat-power',
      'abilities-acquired-melee-combat-speed',
      'abilities-acquired-melee-combat-speed',
      'abilities-acquired-melee-combat-speed',
      'abilities-acquired-melee-combat-other',
      'abilities-acquired-medicine',
      'abilities-acquired-melee-weapon-skills',
    ]);
  });

  it('puts combat mastery and weapon ownership into their dedicated section', () => {
    const migrated = ruleCatalogMigrationService.migrateRules(
      [rule({ code: 'blizhniy-boy', type: 'ability' }), rule({ code: 'vladenie-oruzhiem', type: 'ability' })],
      new Map(),
      mockCombatAbilitySectionByCode,
    );

    expect(migrated.map((entry) => entry.catalogSection)).toEqual([
      'abilities-acquired-melee-mastery',
      'abilities-acquired-melee-mastery',
    ]);
  });

  it('puts the listed precision strikes into the precision section', () => {
    const precisionCodes = [
      'tochnyy-udar',
      'napravlennyy-udar',
      'protivodeystvuyuschiy-udar',
      'udar-v-sochlenenie',
      'smertelnyy-udar',
      'kriticheskiy-udar',
    ];
    const migrated = ruleCatalogMigrationService.migrateRules(
      precisionCodes.map((code) => rule({ code, type: 'ability' })),
      new Map(),
      mockCombatAbilitySectionByCode,
    );

    expect(migrated.every((entry) => entry.catalogSection === 'abilities-acquired-melee-combat-accuracy')).toBe(true);
  });
});
