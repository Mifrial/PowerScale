import { describe, expect, it } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GamePhpOpenStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrike';
import type { GamePhpOpenWideStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenWideStrike';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { strikeReactionService } from '@/modules/Roleplay/Game/Service/Instance/strikeReactionService';

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
    reaction: { trigger: 'strike', window: 'before_defense', performer: 'third_party' },
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

const SHIELD: Rule = {
  mechanics: [],
  id: null,
  code: 'shield',
  type: 'item',
  name: 'Щит',
  description: '',
  spaceId: 1,
  spec: {
    type: 'item',
    category: 'equipment',
    cost_gm: null,
    weight: null,
    special_rule_codes: [],
    block_profile: {
      efficiency: { base: 4, size: 0 },
      defense: { base: 3, size: 0 },
      resistances: [],
    },
  } as RuleSpec,
  createdAt: 1,
};

const COVER: Rule = {
  mechanics: [],
  id: null,
  code: 'prikrytie',
  type: 'ability',
  name: 'Прикрытие',
  description: '',
  spaceId: 1,
  spec: {
    type: 'action',
    zones: {},
    requirements: [],
    grants: [],
    parent_ability_code: null,
    action_components: [{ type: 'resource', resource_code: 'action-points', amount: 2 }],
    reaction: { trigger: 'strike', window: 'before_defense', performer: 'third_party' },
    cover_ally: {
      defense_advantages: [{ delta: -1, source_code: 'circumstances' }],
      reactions: ['block'],
      resolution: { success: 'highest_rating', failure: 'attacker_choice', becomes: 'actual_target' },
      distance: 'declared',
    },
  },
  createdAt: 1,
};

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

const WIDE: GamePhpOpenWideStrike = {
  id: 11,
  attacker: { type: 'character', id: 3 },
  actionRuleCode: 'swing',
  itemInventoryId: 1,
  itemRuleCode: 'sword',
  profileType: 'strike',
  profileIndex: 0,
  targets: [
    { type: 'character', id: 5, answered: false },
    { type: 'character', id: 6, answered: false },
  ],
};

const RULES = [ACTION_POINTS, SUPPORT, SWORD, BOW];

describe('StrikeReactionService', () => {
  it('третья сторона с карточкой и ближним оружием может поддержать', () => {
    const offers = strikeReactionService.offers(STRIKE, 10, [membership({})], [], RULES, false);

    expect(offers).toHaveLength(1);
    const effect = offers[0].effect;
    if (effect.kind !== 'support') throw new Error('expected support');
    expect(effect.sides.map((side) => side.value)).toEqual(['attacker', 'defender']);
    expect(effect.weapons.map((weapon) => weapon.itemRuleCode)).toEqual(['sword']);
    expect(offers[0].reaction).toEqual({ trigger: 'strike', window: 'before_defense', performer: 'third_party' });
  });

  it('атакующий и защитник не кандидаты', () => {
    const rows = [membership({ characterId: 3 }), membership({ characterId: 5 })];

    expect(strikeReactionService.offers(STRIKE, 10, rows, [], RULES, false)).toEqual([]);
  });

  it('дальний удар не поддерживается ближним оружием', () => {
    expect(
      strikeReactionService.offers({ ...STRIKE, profileType: 'shoot' }, 10, [membership({})], [], RULES, false),
    ).toEqual([]);
  });

  it('без подходящего оружия окно не предлагается', () => {
    const rows = [
      membership({ approvedCharacterVersion: version([{ id: 2, ruleCode: 'bow', quantity: 1, equipped: true }]) }),
    ];

    expect(strikeReactionService.offers(STRIKE, 10, rows, [], RULES, false)).toEqual([]);
  });

  it('ведущий может поддержать за НПС', () => {
    const sheet = version([{ id: 1, ruleCode: 'sword', quantity: 1, equipped: true }]);
    const offers = strikeReactionService.offers(STRIKE, 10, [], [npc(8, 'Стражник', sheet)], RULES, true);

    expect(offers).toHaveLength(1);
    expect(offers[0].actor).toMatchObject({ key: 'npc:8', kind: 'npc', id: 8, name: 'Стражник' });
  });

  it('без права вести НПС не предлагается', () => {
    const sheet = version([{ id: 1, ruleCode: 'sword', quantity: 1, equipped: true }]);

    expect(strikeReactionService.offers(STRIKE, 10, [], [npc(8, 'Стражник', sheet)], RULES, false)).toEqual([]);
  });

  it('без купленной карточки окно не предлагается', () => {
    const sheet = version([{ id: 1, ruleCode: 'sword', quantity: 1, equipped: true }]);
    sheet.abilities = [];

    expect(
      strikeReactionService.offers(STRIKE, 10, [membership({ approvedCharacterVersion: sheet })], [], RULES, false),
    ).toEqual([]);
  });

  it('участник, уже объявивший реакцию, не предлагается снова', () => {
    const strike = {
      ...STRIKE,
      supporters: [
        {
          actor: { type: 'character' as const, id: 7 },
          ruleCode: 'podderzhka-v-boyu',
          side: 'attacker',
          advantage: 2,
          source: 'circumstances',
        },
      ],
    };

    expect(strikeReactionService.offers(strike, 10, [membership({})], [], RULES, false)).toEqual([]);
  });

  it('карточка без узла reaction не считается реакцией', () => {
    const withoutTrigger = {
      ...SUPPORT,
      spec: { ...SUPPORT.spec, reaction: undefined },
    } as Rule;

    expect(
      strikeReactionService.offers(
        STRIKE,
        10,
        [membership({})],
        [],
        [ACTION_POINTS, withoutTrigger, SWORD, BOW],
        false,
      ),
    ).toEqual([]);
  });

  it('тело реакции содержит сторону и выбранное оружие', () => {
    const offer = strikeReactionService.offers(STRIKE, 10, [membership({})], [], RULES, false)[0];
    if (offer.effect.kind !== 'support') throw new Error('expected support');
    const request = strikeReactionService.request(
      1,
      offer,
      { kind: 'support', side: 'defender', itemInventoryId: offer.effect.weapons[0].itemInventoryId },
      9,
      4,
      3,
      'key',
    );

    expect(request).toMatchObject({
      gameId: 1,
      battleId: 9,
      expectedVersion: 4,
      action: {
        phase: 'declare',
        kind: 'reaction',
        code: 'podderzhka-v-boyu',
        actor: { type: 'character', id: 7 },
        context: {
          expectedSheetVersion: 3,
          support: {
            side: 'defender',
            itemInventoryId: 1,
            itemRuleCode: 'sword',
            profileType: 'strike',
          },
        },
      },
    });
  });

  it('прикрытие показывается по узлу reaction и не требует support', () => {
    const coverVersion = version([{ id: 1, ruleCode: 'shield', quantity: 1, equipped: true }]);
    coverVersion.abilities = [{ ruleCode: 'prikrytie', level: 1 }];
    const offers = strikeReactionService.offers(
      STRIKE,
      10,
      [membership({ approvedCharacterVersion: coverVersion })],
      [],
      [ACTION_POINTS, SUPPORT, COVER, SWORD, SHIELD],
      false,
    );

    expect(offers).toHaveLength(1);
    const effect = offers[0].effect;
    if (effect.kind !== 'cover') throw new Error('expected cover');
    expect(effect.reactions).toEqual(['block']);
    expect(effect.blockItems.map((item) => item.itemRuleCode)).toEqual(['shield']);

    const request = strikeReactionService.request(
      1,
      offers[0],
      { kind: 'cover', reaction: 'block', itemInventoryId: 1, itemRuleCode: 'shield' },
      9,
      4,
      3,
      'key',
    );
    expect(request.action.context).toEqual({
      expectedSheetVersion: 3,
      cover: {
        reaction: 'block',
        blockItemInventoryId: 1,
        blockItemProfileIndex: 0,
        blockItemRuleCode: 'shield',
      },
    });
  });

  it('прикрытие строки широкого удара адресует заявку конкретной цели', () => {
    const coverVersion = version([{ id: 1, ruleCode: 'shield', quantity: 1, equipped: true }]);
    coverVersion.abilities = [{ ruleCode: 'prikrytie', level: 1 }];
    const members = [membership({ approvedCharacterVersion: coverVersion })];
    const offers = strikeReactionService.wideOffers(
      WIDE,
      WIDE.targets[0],
      10,
      members,
      [],
      [ACTION_POINTS, SUPPORT, COVER, SWORD, SHIELD],
      false,
    );

    expect(offers).toHaveLength(1);
    expect(offers[0].target).toEqual({ type: 'character', id: 5 });
    expect(offers[0].label).toContain('Прикрытие');

    const request = strikeReactionService.request(
      1,
      offers[0],
      { kind: 'cover', reaction: 'block', itemInventoryId: 1, itemRuleCode: 'shield' },
      9,
      4,
      3,
      'key',
      offers[0].target,
    );
    expect(request.action.context).toMatchObject({
      expectedSheetVersion: 3,
      target: { type: 'character', id: 5 },
      cover: { reaction: 'block', blockItemRuleCode: 'shield' },
    });
  });

  it('уже заявившийся прикрывающий не предлагается для этой строки', () => {
    const coverVersion = version([{ id: 1, ruleCode: 'shield', quantity: 1, equipped: true }]);
    coverVersion.abilities = [{ ruleCode: 'prikrytie', level: 1 }];
    const members = [membership({ approvedCharacterVersion: coverVersion })];
    const covered = {
      ...WIDE.targets[0],
      coverers: [{ actor: { type: 'character', id: 7 }, ruleCode: 'prikrytie', reaction: 'block' }],
    };
    const offers = strikeReactionService.wideOffers(
      WIDE,
      covered,
      10,
      members,
      [],
      [ACTION_POINTS, SUPPORT, COVER, SWORD, SHIELD],
      false,
    );

    expect(offers).toHaveLength(0);
  });
});
