import { describe, expect, it } from 'vitest';
import { MagicStudyUnlockService } from '@/modules/Roleplay/Character/Service/MagicStudyUnlockService';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';

const service = new MagicStudyUnlockService();
const orCost = (cost: number) => ({ or: { kind: 'array' as const, levels_cost: [cost] } });

function spell(cost: number, study = true): AbilitySpec {
  return {
    type: 'spell',
    zones: orCost(cost),
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [],
    study: study ? { paths: 'any' } : undefined,
    spell: { power: { base: 3, size: 0 }, control: { base: 3, size: -1 }, duration: { type: 'instant' } },
  };
}

function skill(cost: number, grants: { level: number; grants: Grant[] }[] = [], study = false): AbilitySpec {
  return {
    type: 'skill',
    zones: orCost(cost),
    requirements: [],
    grants,
    parent_ability_code: null,
    study: study ? { paths: 'any' } : undefined,
  };
}

function grant(overrides: Partial<Extract<Grant, { type: 'magic_study' }>> = {}): Grant {
  return { type: 'magic_study', paths: ['arcanist'], max_cost: 2, ...overrides };
}

describe('MagicStudyUnlockService', () => {
  it('гейтит только карточки с правилом study', () => {
    expect(service.isGated(spell(1, false), new Set(['magic']))).toBe(false);
    expect(service.isGated(spell(1), new Set(['magic']))).toBe(true);
    expect(service.isGated(skill(1, [], true), new Set())).toBe(true);
  });

  it('грант с paths открывает изучение на своём пути', () => {
    const unlocks = [{ ...grant(), donor_code: 'opener' }];
    expect(service.isJustified(spell(1), 'arcanist', unlocks)).toBe(true);
    expect(service.isJustified(spell(1), 'psionic', unlocks)).toBe(false);
  });

  it('карточка без подходящего гранта закрыта', () => {
    expect(service.failureReason(spell(1), new Set(['magic']), [])).not.toBeNull();
    expect(service.failureReason(spell(1, false), new Set(['magic']), [])).toBeNull();
  });

  it('потолок стоимости режет карточку', () => {
    const unlocks = [{ ...grant({ max_cost: 2 }), donor_code: 'opener' }];
    expect(service.failureReason(spell(2), new Set(), unlocks)).toBeNull();
    expect(service.failureReason(spell(3), new Set(), unlocks)).not.toBeNull();
  });

  it('оплата из гранта перекрывает каталожную стоимость', () => {
    const unlocks = [{ ...grant({ max_cost: 1, paid_cost: 0 }), donor_code: 'awakening' }];
    expect(service.paidCostOverride(spell(1), unlocks, [], 'arcanist')).toBe(0);
    expect(service.paidCostOverride(spell(1), unlocks, [], 'psionic')).toBeNull();
  });

  it('лимит экземпляров закрывает вторую карточку', () => {
    const unlocks = [{ ...grant({ max_cost: 1, max_instances: 1 }), donor_code: 'awakening' }];
    const learned = [{ ruleCode: 'discharge', domainCode: 'arcanist', spec: spell(1) }];
    expect(service.openPathCodes(spell(1), unlocks, ['arcanist'], learned)).toEqual([]);
    expect(service.isBound(unlocks, learned, 'discharge', 'arcanist')).toBe(true);
  });

  it('any открывает пути, которые грант не перечисляет', () => {
    const unlocks = [{ ...grant({ paths: 'any', max_cost: 1 }), donor_code: 'awakening' }];
    expect(service.openPathCodes(spell(1), unlocks, ['arcanist', 'psionic'], [])).toEqual(['arcanist', 'psionic']);
  });

  it('карточка с ограничением paths не изучается на чужом пути', () => {
    const restricted: AbilitySpec = { ...spell(1), study: { paths: ['psionic'] } };
    const unlocks = [{ ...grant({ paths: 'any' }), donor_code: 'awakening' }];
    expect(service.openPathCodes(restricted, unlocks, ['arcanist', 'psionic'], [])).toEqual(['psionic']);
    expect(service.isJustified(restricted, 'arcanist', unlocks)).toBe(false);
  });

  it('свой грант не открывает карточку сам себе', () => {
    const unlocks = [{ ...grant({ max_cost: 4 }), donor_code: 'advanced' }];
    expect(service.isJustified(spell(2), 'arcanist', unlocks, [], [], undefined, 'advanced')).toBe(false);
    expect(service.isJustified(spell(2), 'arcanist', unlocks, [], [], undefined, 'other')).toBe(true);
  });

  it('разрешает формульный потолок из текущего Интеллекта', () => {
    const unlocks = [
      {
        ...grant({
          max_cost: {
            type: 'to_scalar' as const,
            value: { type: 'characteristic' as const, characteristic_code: 'intellect', modifier: 0 },
          },
        }),
        donor_code: 'construction',
      },
    ];
    const snapshot = {
      abilityLevels: new Map<string, number>(),
      abilityKeywords: new Map<string, Set<string>>(),
      characteristicValues: new Map([['intellect', { base: 4, size: 1 }]]),
      resourceLimits: new Map<string, number | { base: number; size: number }>(),
      keywordCodes: new Set<string>(),
    };

    expect(service.failureReason(spell(4), new Set(), unlocks, [], undefined, snapshot)).toBeNull();
    expect(service.failureReason(spell(5), new Set(), unlocks, [], undefined, snapshot)).not.toBeNull();
  });

  it('runtime-доступность следует текущему Интеллекту и пути', () => {
    const construction = skill(2, [
      {
        level: 1,
        grants: [
          {
            type: 'magic_study',
            paths: ['arcanist'],
            max_cost: {
              type: 'to_scalar',
              value: { type: 'characteristic', characteristic_code: 'intellect', modifier: 0 },
            },
          },
        ],
      },
    ]);
    const rules = [
      { code: 'magic-structure-construction', type: 'ability' as const, spec: construction },
      { code: 'arcanist', type: 'magic_path' as const, spec: { type: 'magic_path' as const } },
    ] as never;
    const abilities = [{ ruleCode: 'magic-structure-construction', level: 1 }];

    expect(
      service.isAvailableForUse(spell(4), 'arcanist', abilities, rules, new Map([['intellect', { base: 4, size: 0 }]])),
    ).toBe(true);
    expect(
      service.isAvailableForUse(spell(5), 'arcanist', abilities, rules, new Map([['intellect', { base: 4, size: 0 }]])),
    ).toBe(false);
    expect(
      service.isAvailableForUse(spell(3), 'psionic', abilities, rules, new Map([['intellect', { base: 4, size: 0 }]])),
    ).toBe(false);
  });

  it('опыт пути суммирует только изученные карточки с study', () => {
    const rules = [
      {
        code: 'psionic-control',
        type: 'ability' as const,
        spec: skill(2, [], false),
      },
      {
        code: 'discharge',
        type: 'ability' as const,
        spec: spell(1),
      },
    ];
    expect(
      service.pathExperience(
        [
          { ruleCode: 'psionic-control', level: 1, domainCode: 'psionic' },
          { ruleCode: 'discharge', level: 1, domainCode: 'psionic' },
        ],
        rules as never,
        'psionic',
        new Map(),
      ),
    ).toBe(1);
  });

  it('прежний path_code читается как один путь', () => {
    const legacy = { type: 'magic_study' as const, max_cost: 2, path_code: 'arcanist' };
    expect(service.isJustified(spell(1), 'arcanist', [{ ...legacy, donor_code: 'opener' }])).toBe(true);
  });

  it('шаман покрывает псионика, обратно нет', () => {
    const rules = [
      { code: 'psionic', type: 'magic_path' as const, spec: { type: 'magic_path' as const, includes_path_codes: [] } },
      {
        code: 'shaman',
        type: 'magic_path' as const,
        spec: { type: 'magic_path' as const, includes_path_codes: ['psionic'] },
      },
      { code: 'discharge', type: 'ability' as const, spec: spell(1) },
    ];
    expect([...service.coveredPathCodes(rules as never, 'shaman')].sort()).toEqual(['psionic', 'shaman']);
    expect(service.pathCovers(rules as never, 'shaman', 'psionic')).toBe(true);
    expect(service.pathCovers(rules as never, 'psionic', 'shaman')).toBe(false);
    expect(
      service.knownOnPath(
        [{ ruleCode: 'discharge', level: 1, domainCode: 'psionic' }],
        'discharge',
        'shaman',
        rules as never,
      ),
    ).toBe(true);
    expect(
      service.knownOnPath(
        [{ ruleCode: 'discharge', level: 1, domainCode: 'shaman' }],
        'discharge',
        'psionic',
        rules as never,
      ),
    ).toBe(false);
  });
});
