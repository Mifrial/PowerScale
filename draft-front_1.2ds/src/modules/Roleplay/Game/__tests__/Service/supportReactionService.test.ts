import { describe, expect, it } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GamePhpOpenStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrike';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { supportReactionService } from '@/modules/Roleplay/Game/Service/Instance/supportReactionService';

const ACTION_POINTS: Rule = {
  mechanics: [],
  id: null,
  code: 'action-points',
  type: 'resource',
  name: 'Очки действий',
  description: '',
  spaceId: 1,
  spec: { is_dimensional: false, auto_add: true },
  createdAt: 1,
};

const SUPPORT: Rule = {
  mechanics: [],
  id: null,
  code: 'podderzhka-v-boyu',
  type: 'ability',
  name: 'Поддержка в бою',
  description: '',
  spaceId: 1,
  spec: {
    type: 'action',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [{ type: 'resource', resource_code: 'action-points', amount: 2 }],
    support: {
      sides: ['attacker', 'defender'],
      advantage: 2,
      profile: 'strike',
      min_mastery: { base: 4, size: 0 },
      source: 'circumstances',
    },
  },
  createdAt: 1,
};

function weaponRule(code: string, name: string, profile: 'strike' | 'shoot'): Rule {
  const spec = {
    type: 'item',
    category: 'equipment',
    cost_gm: null,
    weight: null,
    special_rule_codes: [],
    weapon: {
      min_strength: null,
      weapon_profiles: [
        {
          type: profile,
          distance: {
            min: { type: 'step', value: { type: 'fixed', value: 1 } },
            max: null,
          },
          damage: { formula: { type: 'fixed', value: 1 }, damage_type_code: null },
          penetration: { type: 'fixed', value: 0 },
          accuracy: { base: 0, size: 0 },
        },
      ],
    },
  };

  return {
    mechanics: [],
    id: null,
    code,
    type: 'item',
    name,
    description: '',
    spaceId: 1,
    spec: spec as RuleSpec,
    createdAt: 1,
  };
}

const SWORD = weaponRule('sword', 'Меч', 'strike');
const BOW = weaponRule('bow', 'Лук', 'shoot');

function version(inventory: CharacterVersion['inventory']): CharacterVersion {
  const base = structuredClone(versions[1]);
  base.abilities = [{ ruleCode: 'podderzhka-v-boyu', level: 1 }];
  base.inventory = inventory;

  return base;
}

function membership(overrides: Partial<GameCharacterMembership>): GameCharacterMembership {
  return {
    gameId: 1,
    characterId: 7,
    characterName: 'Поддержка',
    characterOwnerId: 10,
    characterOwnerName: 'Игрок',
    role: 'player',
    membershipStatus: 'active',
    isActiveSessionParticipant: true,
    approvedCharacterVersion: version([{ id: 1, ruleCode: 'sword', quantity: 1, equipped: true }]),
    reviewState: 'clean',
    membershipRevision: 1,
    returnedAt: null,
    returnReason: null,
    returnMessageId: null,
    overlay: null,
    visibility: [],
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: '',
    ...overrides,
  };
}

function npc(id: number, name: string, sheet: CharacterVersion): GameNpc {
  return {
    id,
    gameId: 1,
    name,
    shortDescription: null,
    fullDescription: null,
    tags: [],
    version: sheet,
    actualVersion: 1,
    status: 'active',
    proposedBy: null,
    visibility: [],
    updatedAt: '',
  };
}

const STRIKE: GamePhpOpenStrike = {
  attackerKind: 'character',
  attackerId: 3,
  defenderKind: 'character',
  defenderId: 5,
  actionRuleCode: 'swing',
  itemInventoryId: 1,
  itemRuleCode: 'sword',
  profileType: 'strike',
  profileIndex: 0,
};

const RULES = [ACTION_POINTS, SUPPORT, SWORD, BOW];

describe('SupportReactionService', () => {
  it('третья сторона с карточкой и ближним оружием может поддержать', () => {
    const options = supportReactionService.options(STRIKE, 10, [membership({})], [], RULES, false);

    expect(options).toHaveLength(1);
    expect(options[0].sides).toEqual(['attacker', 'defender']);
    expect(options[0].weapons.map((weapon) => weapon.itemRuleCode)).toEqual(['sword']);
  });

  it('атакующий и защитник не кандидаты', () => {
    const rows = [membership({ characterId: 3 }), membership({ characterId: 5 })];

    expect(supportReactionService.options(STRIKE, 10, rows, [], RULES, false)).toEqual([]);
  });

  it('дальний удар не поддерживается ближним оружием', () => {
    expect(
      supportReactionService.options({ ...STRIKE, profileType: 'shoot' }, 10, [membership({})], [], RULES, false),
    ).toEqual([]);
  });

  it('без подходящего оружия окно не предлагается', () => {
    const rows = [
      membership({ approvedCharacterVersion: version([{ id: 2, ruleCode: 'bow', quantity: 1, equipped: true }]) }),
    ];

    expect(supportReactionService.options(STRIKE, 10, rows, [], RULES, false)).toEqual([]);
  });

  it('ведущий может поддержать за НПС', () => {
    const sheet = version([{ id: 1, ruleCode: 'sword', quantity: 1, equipped: true }]);
    const options = supportReactionService.options(STRIKE, 10, [], [npc(8, 'Стражник', sheet)], RULES, true);

    expect(options).toHaveLength(1);
    expect(options[0].actor).toMatchObject({ key: 'npc:8', kind: 'npc', id: 8, name: 'Стражник' });
  });

  it('без права вести НПС не предлагается', () => {
    const sheet = version([{ id: 1, ruleCode: 'sword', quantity: 1, equipped: true }]);

    expect(supportReactionService.options(STRIKE, 10, [], [npc(8, 'Стражник', sheet)], RULES, false)).toEqual([]);
  });

  it('без купленной карточки окно не предлагается', () => {
    const sheet = version([{ id: 1, ruleCode: 'sword', quantity: 1, equipped: true }]);
    sheet.abilities = [];

    expect(
      supportReactionService.options(STRIKE, 10, [membership({ approvedCharacterVersion: sheet })], [], RULES, false),
    ).toEqual([]);
  });

  it('тело поддержки содержит сторону и выбранное оружие', () => {
    const option = supportReactionService.options(STRIKE, 10, [membership({})], [], RULES, false)[0];
    const request = supportReactionService.request(1, option, 'defender', option.weapons[0], 9, 4, 3, 'key');

    expect(request).toMatchObject({
      gameId: 1,
      battleId: 9,
      expectedVersion: 4,
      action: {
        phase: 'declare',
        kind: 'support',
        code: 'podderzhka-v-boyu',
        actor: { type: 'character', id: 7 },
        context: {
          expectedSheetVersion: 3,
          side: 'defender',
          itemInventoryId: 1,
          itemRuleCode: 'sword',
          profileType: 'strike',
        },
      },
    });
  });
});
