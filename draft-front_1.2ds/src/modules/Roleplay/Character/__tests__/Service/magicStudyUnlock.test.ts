import { describe, expect, it } from 'vitest';
import { MagicStudyUnlockService } from '@/modules/Roleplay/Character/Service/MagicStudyUnlockService';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';

const service = new MagicStudyUnlockService();
const orCost = (cost: number) => ({ or: { kind: 'array' as const, levels_cost: [cost] } });

function spell(cost: number): AbilitySpec {
  return {
    type: 'spell',
    zones: orCost(cost),
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [],
    spell: { power: { base: 3, size: 0 }, control: { base: 3, size: -1 }, duration: { type: 'instant' } },
  };
}

function skill(cost: number, grants: { level: number; grants: Grant[] }[] = []): AbilitySpec {
  return {
    type: 'skill',
    zones: orCost(cost),
    requirements: [],
    grants,
    parent_ability_code: null,
  };
}

describe('MagicStudyUnlockService', () => {
  it('не гейтит становление с грантом пути', () => {
    const becoming = skill(2, [{ level: 1, grants: [{ type: 'magic_path', path_code: 'arcanist' }] }]);
    expect(service.isGated(becoming, new Set(['magic']))).toBe(false);
    expect(service.failureReason(becoming, new Set(['magic']), [])).toBeNull();
  });

  it('грант изучения делает навык входным, без чужого magic_study', () => {
    const awakening = skill(0, [
      { level: 1, grants: [{ type: 'magic_study', scope: 'spell', max_cost: 1, path_code: 'psionic' }] },
    ]);
    expect(service.isGated(awakening, new Set(['magic']))).toBe(false);
  });

  it('лимит экземпляров закрывает второе заклинание', () => {
    const unlocks = [
      { type: 'magic_study' as const, scope: 'spell' as const, max_cost: 1, max_instances: 1, paid_cost: 0 },
    ];
    const learned = [{ ruleCode: 'discharge', domainCode: 'psionic', spec: spell(1) }];
    expect(service.failureReason(spell(1), new Set(['magic']), unlocks, learned, 'lightning-strike')).toContain(
      'заклинаний',
    );
    expect(service.failureReason(spell(1), new Set(['magic']), unlocks, learned, 'discharge')).toBeNull();
  });

  it('оплата из гранта перекрывает каталожную стоимость', () => {
    const unlocks = [
      {
        type: 'magic_study' as const,
        scope: 'spell' as const,
        max_cost: 1,
        path_code: 'psionic',
        max_instances: 1,
        paid_cost: 0,
      },
    ];
    expect(service.paidCostOverride(spell(1), unlocks, [], 'psionic')).toBe(0);
    expect(service.paidCostOverride(spell(1), unlocks, [], 'arcanist')).toBeNull();
  });

  it('занятый слот пробуждения не даёт второе заклинание за 0', () => {
    const unlocks = [
      {
        type: 'magic_study' as const,
        scope: 'spell' as const,
        max_cost: 1,
        path_code: 'psionic',
        max_instances: 1,
        paid_cost: 0,
      },
    ];
    const learned = [{ ruleCode: 'discharge', domainCode: 'psionic', spec: spell(1) }];
    expect(service.paidCostOverride(spell(1), unlocks, learned, 'psionic')).toBeNull();
    expect(
      service.paidCostOverride(spell(1), unlocks, learned, 'psionic', {
        ruleCode: 'discharge',
        domainCode: 'psionic',
      }),
    ).toBe(0);
    expect(service.openPathCodes(spell(1), unlocks, ['arcanist'], learned)).toEqual([]);
    expect(service.isBound(unlocks, learned, 'discharge', 'psionic')).toBe(true);
  });

  it('безлимитный грант пути открывает путь, слот пробуждения — только свой', () => {
    const unlocks = [
      {
        type: 'magic_study' as const,
        scope: 'spell' as const,
        max_cost: 1,
        path_code: 'psionic',
        max_instances: 1,
        paid_cost: 0,
      },
      { type: 'magic_study' as const, scope: 'spell' as const, max_cost: 2, path_code: 'arcanist' },
    ];
    expect(service.openPathCodes(spell(1), unlocks, ['arcanist'], [])).toEqual(['psionic', 'arcanist']);
    const learned = [{ ruleCode: 'discharge', domainCode: 'psionic', spec: spell(1) }];
    expect(service.openPathCodes(spell(1), unlocks, ['arcanist'], learned)).toEqual(['arcanist']);
  });

  it('грант без пути не открывает чужие дарованные пути', () => {
    const unlocks = [{ type: 'magic_study' as const, scope: 'spell' as const, max_cost: 2 }];
    expect(service.openPathCodes(spell(1), unlocks, ['arcanist', 'psionic'], [])).toEqual([]);
  });

  it('закрывает заклинание без гранта изучения', () => {
    expect(service.failureReason(spell(1), new Set(['magic']), [])).toContain('заклинаний');
  });

  it('открывает заклинания до потолка стоимости', () => {
    const unlocks = [{ type: 'magic_study' as const, scope: 'spell' as const, max_cost: 2 }];
    expect(service.failureReason(spell(1), new Set(['magic']), unlocks)).toBeNull();
    expect(service.failureReason(spell(2), new Set(['magic']), unlocks)).toBeNull();
    expect(service.failureReason(spell(3), new Set(['magic']), unlocks)).not.toBeNull();
    expect(service.failureReason(skill(1), new Set(['magic']), unlocks)).not.toBeNull();
  });

  it('второй грант открывает не-spell с тем же потолком', () => {
    const unlocks = [{ type: 'magic_study' as const, scope: 'non_spell' as const, max_cost: 2 }];
    expect(service.failureReason(skill(2), new Set(['magic']), unlocks)).toBeNull();
    expect(service.failureReason(spell(1), new Set(['magic']), unlocks)).not.toBeNull();
  });

  it('опыт пути суммирует стоимость способностей с доменом или признаком пути', () => {
    const rules = [
      {
        code: 'psionic-control',
        type: 'ability' as const,
        keywordIds: [230],
        spec: skill(2),
      },
      {
        code: 'discharge',
        type: 'ability' as const,
        keywordIds: [],
        spec: spell(1),
      },
    ];
    const keywordCodeById = new Map([[230, 'psionic']]);
    expect(
      service.pathExperience(
        [
          { ruleCode: 'psionic-control', level: 1 },
          { ruleCode: 'discharge', level: 1, domainCode: 'psionic' },
        ],
        rules as never,
        'psionic',
        keywordCodeById,
      ),
    ).toBe(3);
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
