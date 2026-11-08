import { describe, expect, it } from 'vitest';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { LastStrikeSnapshot } from '@/modules/Roleplay/Game/Dto/LastStrikeSnapshot';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { StrikeManeuverService } from '@/modules/Roleplay/Game/Service/StrikeManeuverService';

const service = new StrikeManeuverService();

const maneuver: Rule = {
  mechanics: [],
  id: null,
  code: 'razbit-zaschitu',
  type: 'ability',
  name: 'Разбить защиту',
  description: '',
  spaceId: 1,
  spec: {
    type: 'action',
    zones: { or: { kind: 'array', levels_cost: [2] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [
      { type: 'resource', resource_code: 'action-points', amount: 1, label: 'Действие' },
    ],
    strike_maneuver: {
      min_success: 3,
      min_weapon_mastery: 3,
      effects: [{ kind: 'state', state: 'unstable', amount: 1 }],
    },
  },
  createdAt: 1,
};

const boost: Rule = {
  mechanics: [],
  id: null,
  code: 'vskryt-slabost',
  type: 'ability',
  name: 'Вскрыть слабость',
  description: '',
  spaceId: 1,
  spec: {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [2] } },
    requirements: [],
    grants: [],
    parent_ability_code: null,
    maneuver_boost: {
      maneuvers: ['razbit-zaschitu'],
      min_success: 4,
      min_weapon_mastery: 5,
      replaces: [{ kind: 'state', state: 'unstable' }],
      effects: [{ kind: 'state', state: 'unstable', amount: 2 }],
    },
  },
  createdAt: 1,
};

const unstable: Rule = {
  mechanics: [],
  id: null,
  code: 'unstable',
  type: 'state',
  name: 'Неустойчивость',
  description: '',
  spaceId: 1,
  createdAt: 1,
};

const rules: Rule[] = [maneuver, boost, unstable];

function ability(ruleCode: string, level = 1): CharacterAbility {
  return { ruleCode, level };
}

function snapshot(attackSr: number, targetKey = 'character:2'): LastStrikeSnapshot {
  return { kind: 'other', hits: [{ targetKey, attackSr }] };
}

describe('StrikeManeuverService', () => {
  it('показывает купленный манёвр сразу после удара с нужным РУ', () => {
    const options = service.listAvailable([ability('razbit-zaschitu')], rules, snapshot(4));

    expect(options).toHaveLength(1);
    expect(options[0]).toMatchObject({
      ruleCode: 'razbit-zaschitu',
      cost: 1,
      minSuccess: 3,
      targetKey: 'character:2',
    });
    expect(options[0].label).toBe('1 ОД · 3РУ (мастерство 3) → Неустойчивость 1');
  });

  it('без прошлого удара или с низким РУ манёвр недоступен', () => {
    expect(service.listAvailable([ability('razbit-zaschitu')], rules, null)).toEqual([]);
    expect(service.listAvailable([ability('razbit-zaschitu')], rules, snapshot(2))).toEqual([]);
  });

  it('не показывает карточку без уровня', () => {
    expect(service.listAvailable([ability('razbit-zaschitu', 0)], rules, snapshot(4))).toEqual([]);
  });

  it('добавляет ступень усиления, если навык куплен', () => {
    const options = service.listAvailable(
      [ability('razbit-zaschitu'), ability('vskryt-slabost')],
      rules,
      snapshot(4),
    );

    expect(options[0].label).toBe(
      '1 ОД · 3РУ (мастерство 3) → Неустойчивость 1, 4РУ (мастерство 5) → Неустойчивость 2',
    );
  });

  it('цели прошлого удара для гейта действия', () => {
    expect(service.followUpTargetKeys(maneuver, snapshot(3))).toEqual(['character:2']);
    expect(service.followUpTargetKeys(maneuver, snapshot(2))).toEqual([]);
    expect(service.followUpTargetKeys(maneuver, null)).toEqual([]);
    expect(service.followUpTargetKeys(boost, snapshot(4))).toBeNull();
  });
});
